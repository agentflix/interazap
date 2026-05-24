import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { EventsGateway } from '../../realtime/gateways/events.gateway';
import {
  JsonRecord,
  getBoolean,
  getString,
} from '../../../shared/utils/type-guards';
import { CHAT_EVENTS } from '../../../shared/constants';
import {
  PayloadSemanticMetadata,
  StreamPayload,
  UazapiStreamPayload,
} from './chat-webhook.types';
import { ChatWebhookEventNormalizer } from './chat-webhook-event-normalizer.service';
import { TicketResolverService } from './ticket-resolver.service';
import { WebhookRealtimeEmitter } from './webhook-realtime-emitter.service';
import { ConnectionStatusService } from './connection-status.service';

/**
 * Roteia eventos de webhook normalizados para emissao realtime via WebSocket.
 * Cada tipo de evento (mensagem, status, edicao, conexao, typing) e tratado
 * por um metodo dedicado.
 */
@Injectable()
export class ChatWebhookRealtimeProcessor {
  private readonly logger = new Logger(ChatWebhookRealtimeProcessor.name);

  constructor(
    private readonly configService: ConfigService,
    private readonly redisService: RedisService,
    private readonly eventsGateway: EventsGateway,
    private readonly eventNormalizer: ChatWebhookEventNormalizer,
    readonly realtimeEmitter: WebhookRealtimeEmitter,
    private readonly ticketResolver: TicketResolverService,
    private readonly connectionStatusService: ConnectionStatusService,
  ) {}

  /**
   * Delega resolucao de JID remoto para o servico de tickets.
   *
   * @param message - Record de mensagem normalizado
   * @returns JID remoto ou null quando nao identificado
   */
  resolveRemoteJid(message: JsonRecord): string | null {
    return this.ticketResolver.resolveRemoteJid(message);
  }

  /**
   * Resolve o ID do ticket associado ao JID remoto via cache Redis.
   *
   * @param tenantId - ID do tenant
   * @param remoteJid - JID remoto da conversa
   * @returns ID do ticket ou null quando nao encontrado
   */
  async resolveTicketIdForRemoteJid(
    tenantId: string,
    remoteJid: string | null,
  ): Promise<string | null> {
    return this.ticketResolver.resolveTicketIdForRemoteJid(tenantId, remoteJid);
  }

  /**
   * Atualiza o status de conexao da instancia no banco de dados.
   *
   * @param instanceId - ID da instancia a atualizar
   * @param status - Novo status de conexao
   * @param payload - Payload do evento de conexao
   */
  async updateInstanceConnectionStatus(
    instanceId: string,
    status: string,
    payload: StreamPayload,
  ): Promise<void> {
    await this.connectionStatusService.updateInstanceConnectionStatus(
      instanceId,
      status,
      payload,
      this.eventNormalizer.extractConnection(payload),
    );
  }

  /**
   * Emite eventos realtime adequados para cada tipo de evento normalizado.
   *
   * @param payload - Payload de stream a emitir
   * @param instanceId - ID da instancia opcional
   * @param precomputedSemantics - Semantica pre-calculada para evitar reprocessamento
   */
  async emitRealtime(
    payload: StreamPayload,
    instanceId?: string,
    precomputedSemantics?: PayloadSemanticMetadata,
  ): Promise<void> {
    const fallbackEvent = this.eventNormalizer.isFallbackPayload(payload)
      ? payload.payload.event_type
      : undefined;
    const eventType = (payload.event_type ?? fallbackEvent ?? '')
      .toString()
      .toLowerCase();
    const semantics =
      precomputedSemantics ??
      this.eventNormalizer.resolvePayloadSemantics(payload);

    if (eventType.startsWith('connection')) {
      this.emitConnectionEvent(payload, instanceId);
      return;
    }

    if (semantics.semanticType === 'presence') {
      await this.emitTypingEvent(payload as UazapiStreamPayload);
      return;
    }

    if (eventType === 'messages_update') {
      this.emitMessageStatusEvent(payload);
      return;
    }

    if (eventType === 'messages' && semantics.changeKind === 'edited') {
      await this.emitMessageEditEvent(payload);
      return;
    }

    if (eventType === 'messages') {
      this.logger.debug(
        'Emitting fast-path inbound realtime for messages (preliminary)',
      );
      await this.emitFastPathMessage(payload);
      return;
    }
  }

  /**
   * Emite evento preliminar de mensagem recebida (fast-path).
   *
   * @param payload - Payload de stream do evento de mensagem
   */
  private async emitFastPathMessage(payload: StreamPayload): Promise<void> {
    const tenantId = payload.tenant_id;
    if (!tenantId) {
      this.logger.debug(
        'No tenant_id in messages event — skipping fast-path realtime emit',
      );
      return;
    }

    const message = this.eventNormalizer.extractMessagePayload(payload);
    if (!message) {
      this.logger.debug(
        'No message data found in messages event — skipping fast-path realtime emit',
      );
      return;
    }

    const remoteJid = this.resolveRemoteJid(message);
    let ticketId: string | null = null;
    try {
      ticketId = await this.resolveTicketIdForRemoteJid(tenantId, remoteJid);
    } catch {
      // Cache miss is acceptable; skip ticket room
    }

    const messageId =
      getString(message, 'id') ?? getString(message, 'messageId') ?? null;

    // Resolve message type — contacts arrive as type="media" with mediaType="contact_array"
    const rawType = getString(message, 'type') ?? 'text';
    const mediaType = (
      getString(message, 'mediaType') ??
      getString(message, 'media_type') ??
      ''
    ).toLowerCase();
    const messageType = (
      getString(message, 'messageType') ??
      getString(message, 'message_type') ??
      ''
    ).toLowerCase();

    const contactTokens = [
      'contact',
      'contacts',
      'contact_array',
      'contactmessage',
      'contactsarraymessage',
    ];
    const isContact =
      contactTokens.includes(rawType.toLowerCase()) ||
      contactTokens.includes(mediaType) ||
      contactTokens.includes(messageType);

    const isLocation =
      rawType.toLowerCase() === 'location' ||
      mediaType === 'location' ||
      messageType === 'locationmessage';

    const resolvedType = isContact
      ? 'contact'
      : isLocation
        ? 'location'
        : rawType;

    // Build minimal metadata for contact messages
    let preliminaryMetadata: Record<string, unknown> | undefined;
    if (isContact) {
      const content = message.content as Record<string, unknown> | undefined;
      const media = message.media as Record<string, unknown> | undefined;
      const contacts = (content?.contacts ?? media?.contacts ?? []) as Array<
        Record<string, unknown>
      >;
      if (Array.isArray(contacts) && contacts.length > 0) {
        preliminaryMetadata = {
          contact: {
            contacts: contacts.map((c: Record<string, unknown>) => ({
              fullName: c.displayName ?? c.fullName ?? c.name ?? null,
              phoneNumber: c.phoneNumber ?? c.phone ?? null,
            })),
          },
        };
      }
    }

    const fastPayload = {
      event: CHAT_EVENTS.ACTIVITY,
      chatId: ticketId,
      ticketId,
      tenant_id: tenantId,
      timestamp: new Date().toISOString(),
      preliminary: true,
      subevents: [
        {
          type: 'msg.received',
          data: {
            ticket_id: ticketId,
            tenant_id: tenantId,
            message: {
              id: messageId,
              content:
                getString(message, 'body') ??
                getString(message, 'text') ??
                getString(message, 'caption') ??
                '',
              direction: getBoolean(message, 'fromMe')
                ? 'outgoing'
                : 'incoming',
              type: resolvedType,
              is_from_contact: !(getBoolean(message, 'fromMe') ?? false),
              external_id: messageId,
              created_at: new Date().toISOString(),
              ...(preliminaryMetadata ? { metadata: preliminaryMetadata } : {}),
            },
          },
        },
      ],
    };

    // Always emit to tenant room
    this.realtimeEmitter.emitToTenant(
      tenantId,
      CHAT_EVENTS.ACTIVITY,
      fastPayload as Record<string, unknown>,
    );
    // Also emit to ticket room if we resolved ticket
    if (ticketId) {
      this.realtimeEmitter.emitToTicket(
        ticketId,
        CHAT_EVENTS.ACTIVITY,
        fastPayload as Record<string, unknown>,
      );
    }
  }

  /**
   * Emite evento de edicao de mensagem para o tenant e sala do ticket.
   *
   * @param payload - Payload de stream do evento de edicao
   */
  private async emitMessageEditEvent(payload: StreamPayload): Promise<void> {
    const tenantId = payload.tenant_id;
    if (!tenantId) {
      this.logger.debug(
        'No tenant_id in edited messages event — skipping realtime emit',
      );
      return;
    }

    const message = this.eventNormalizer.extractMessagePayload(payload);
    const rawMessage = this.eventNormalizer.extractRawMessagePayload(payload);
    const messageId = this.eventNormalizer.resolveEditedMessageId(payload);
    if (!messageId) {
      this.logger.debug(
        'No edited message reference found — skipping edit realtime emit',
      );
      return;
    }

    const content = this.eventNormalizer.extractMessageContent(
      message,
      rawMessage,
    );
    if (content.trim() === '') {
      this.logger.debug(
        `No resolved content for edited message ${messageId} — skipping edit realtime emit`,
      );
      return;
    }

    const remoteJid = this.resolveRemoteJid(message ?? rawMessage ?? {});
    const ticketId = await this.resolveTicketIdForRemoteJid(
      tenantId,
      remoteJid,
    );
    const resolvedTicketId = ticketId?.trim();
    if (!resolvedTicketId) {
      this.logger.debug(
        `No ticket resolved for edited message ${messageId} (remoteJid: ${remoteJid ?? 'n/a'}) — skipping edit realtime emit`,
      );
      return;
    }

    const eventData = {
      message_id: messageId,
      external_id: messageId,
      ticket_id: resolvedTicketId,
      tenant_id: tenantId,
      content,
      is_edited: true,
    };

    this.logger.debug(
      `Emitting chat.message.edit for message ${messageId} to tenant:${tenantId}`,
    );

    this.realtimeEmitter.emitToTenant(
      tenantId,
      CHAT_EVENTS.MESSAGE_EDIT,
      eventData,
    );

    this.realtimeEmitter.emitToTicket(
      resolvedTicketId,
      CHAT_EVENTS.MESSAGE_EDIT,
      eventData,
    );

    const activityPayload = {
      event: CHAT_EVENTS.ACTIVITY,
      chatId: resolvedTicketId,
      ticketId: resolvedTicketId,
      tenantId,
      timestamp: new Date().toISOString(),
      subevents: [
        {
          type: 'msg.edit' as const,
          data: eventData,
        },
      ],
    };

    this.realtimeEmitter.emitToTenant(
      tenantId,
      CHAT_EVENTS.ACTIVITY,
      activityPayload as Record<string, unknown>,
    );

    this.realtimeEmitter.emitToTicket(
      resolvedTicketId,
      CHAT_EVENTS.ACTIVITY,
      activityPayload as Record<string, unknown>,
    );
  }

  /**
   * Emite evento de nova mensagem diretamente via WebSocket (PATH A - baixa latencia).
   *
   * @param payload - Payload de stream do evento de nova mensagem
   */
  async emitNewMessageEvent(payload: StreamPayload): Promise<void> {
    const message = this.eventNormalizer.extractMessagePayload(payload);
    if (!message) {
      this.logger.debug(
        'No message data found in messages event — skipping realtime emit',
      );
      return;
    }

    const tenantId = payload.tenant_id;
    if (!tenantId) {
      this.logger.debug(
        'No tenant_id in messages event — skipping realtime emit',
      );
      return;
    }

    const messageId =
      getString(message, 'id') ?? getString(message, 'messageId') ?? null;
    const remoteJid = this.resolveRemoteJid(message);
    const ticketId = await this.resolveTicketIdForRemoteJid(
      payload.tenant_id,
      remoteJid,
    );

    const eventData = {
      ticket_id: ticketId,
      message: {
        id: messageId,
        content:
          getString(message, 'text') ??
          getString(message, 'body') ??
          getString(message, 'caption') ??
          null,
        type: getString(message, 'type') ?? 'text',
        direction: getBoolean(message, 'fromMe') ? 'outgoing' : 'incoming',
        status: 'received',
        created_at: getString(message, 'timestamp') ?? new Date().toISOString(),
        from: getString(message, 'from') ?? null,
        to: getString(message, 'to') ?? null,
        fromMe: getBoolean(message, 'fromMe') ?? false,
        isGroup: getBoolean(message, 'isGroup') ?? false,
        mediaUrl: getString(message, 'mediaUrl') ?? null,
        mimeType: getString(message, 'mimeType') ?? null,
        fileName: getString(message, 'fileName') ?? null,
        provider: payload.provider ?? null,
        instance_webhook_token: payload.instance_webhook_token ?? null,
      },
    };

    this.logger.debug(
      `Emitting chat.message.new (fast path) for message ${messageId} to tenant:${tenantId}`,
    );

    this.realtimeEmitter.emitToTenant(
      tenantId,
      CHAT_EVENTS.MESSAGE_NEW,
      eventData,
    );
  }

  /**
   * Emite evento chat.typing diretamente via WebSocket.
   *
   * @param payload - Payload de stream de presence
   */
  private async emitTypingEvent(payload: UazapiStreamPayload): Promise<void> {
    const number = payload.number;
    if (!number) {
      this.logger.warn('Presence event without number, skipping');
      return;
    }

    // Resolver ticket ativo pelo numero do contato
    const ticketId = await this.resolveTicketIdForRemoteJid(
      payload.tenant_id,
      `${number}@s.whatsapp.net`,
    );

    const isTyping = payload.is_typing ?? true;
    const typingPayload = {
      ticket_id: ticketId ?? undefined,
      is_typing: isTyping,
      presence: payload.presence,
      number,
    };

    // Emitir para sala do tenant
    this.realtimeEmitter.emitToTenant(
      payload.tenant_id,
      CHAT_EVENTS.TYPING,
      typingPayload,
    );

    // Emitir para sala do ticket (se existir)
    if (ticketId) {
      this.realtimeEmitter.emitToTicket(
        ticketId,
        CHAT_EVENTS.TYPING,
        typingPayload,
      );
    }

    this.logger.debug(
      `Emitted chat.typing: ${isTyping ? 'start' : 'stop'} for ${number}`,
    );
  }

  /**
   * Emite evento de conexao de instancia com throttle para estados transicionais.
   *
   * @param payload - Payload de stream do evento
   * @param instanceId - ID da instancia opcional
   */
  private emitConnectionEvent(
    payload: StreamPayload,
    instanceId?: string,
  ): void {
    this.connectionStatusService.emitConnectionEvent(
      payload,
      instanceId,
      this.eventNormalizer.extractConnection(payload),
      this.eventNormalizer.extractStatusPayload(payload),
    );
  }

  /**
   * Emite evento de atualizacao de status de mensagem via WebSocket.
   *
   * @param payload - Payload de stream do evento messages_update
   */
  private emitMessageStatusEvent(payload: StreamPayload): void {
    // O payload pode ter raw.raw.event (estrutura aninhada do Uazapi)
    const outerRaw = payload.raw;
    const innerRaw = outerRaw?.raw as Record<string, unknown> | undefined;
    const event = (innerRaw?.event ?? outerRaw?.event) as
      | Record<string, unknown>
      | undefined;

    // Extrai MessageIDs
    const messageIds = (event?.MessageIDs ?? []) as string[];
    if (messageIds.length === 0) {
      this.logger.debug('No MessageIDs found in messages_update event');
      return;
    }

    // Extrai o tipo de status (Delivered, Read, etc)
    const statusType = (
      (event?.Type as string) ??
      (innerRaw?.state as string) ??
      (outerRaw?.state as string) ??
      'unknown'
    ).toLowerCase();

    // Mapeia para status normalizado
    const normalizedStatus =
      this.eventNormalizer.normalizeMessageStatus(statusType);
    if (!normalizedStatus) {
      this.logger.debug(`Unknown message status type: ${statusType}`);
      return;
    }

    const tenantId = payload.tenant_id;
    const now = new Date().toISOString();

    // Emite evento para cada mensagem no array
    for (const messageId of messageIds) {
      const eventData = {
        message_id: messageId,
        external_id: messageId,
        status: normalizedStatus,
        delivered_at: normalizedStatus === 'delivered' ? now : undefined,
        read_at: normalizedStatus === 'read' ? now : undefined,
      };

      this.logger.debug(
        `Emitting chat.message.status for message ${messageId} -> ${normalizedStatus}`,
      );

      // Emite para room do tenant
      if (tenantId) {
        this.realtimeEmitter.emitToTenant(
          tenantId,
          CHAT_EVENTS.MESSAGE_STATUS,
          eventData,
        );
      }
    }
  }
}
