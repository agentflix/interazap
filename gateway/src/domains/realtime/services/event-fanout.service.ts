import {
  Injectable,
  OnModuleInit,
  OnModuleDestroy,
  Logger,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { EventsGateway } from '../gateways/events.gateway';
import { GatewayFileLogger } from '../../../common/logger/gateway-file-logger';
import type { Redis } from 'ioredis';
import {
  getRecord,
  getString,
  isRecord,
} from '../../../shared/utils/type-guards';
import { GatewayConfigService } from '../../../shared/services/gateway-config.service';
import { CHAT_EVENTS, tenantRoom } from '../../../shared/constants';
import { GatewayEvent } from '../models/event-fanout.model';

/**
 * EventFanoutService
 *
 * Serviço responsável por consumir eventos do canal Redis PubSub `ws.events`
 * e distribuí-los via WebSocket para os clientes conectados.
 * Garante isolação por tenant e validação de ownership das rooms.
 */
@Injectable()
export class EventFanoutService implements OnModuleInit, OnModuleDestroy {
  private readonly logger = new Logger(EventFanoutService.name);
  private readonly fileLogger = new GatewayFileLogger(EventFanoutService.name);
  private subscriberClient: Redis | null = null;
  private readonly debugChatActivity: boolean;

  constructor(
    private readonly configService: ConfigService,
    private readonly gatewayConfigService: GatewayConfigService,
    private readonly redisService: RedisService,
    private readonly eventsGateway: EventsGateway,
  ) {
    this.debugChatActivity =
      this.configService.get<boolean>('REALTIME_DEBUG_CHAT_ACTIVITY') ===
        true ||
      String(
        this.configService.get<string>('REALTIME_DEBUG_CHAT_ACTIVITY'),
      ).toLowerCase() === 'true';
  }

  /**
   * Inicializa o subscriber Redis e começa a escutar o canal `ws.events`.
   * Configura handlers de erro e mensagem para processar eventos em tempo real.
   */
  async onModuleInit() {
    this.logger.log('Initializing EventFanoutService...');

    try {
      // Use the dedicated pub/sub client (separate connection to avoid blocking commands)
      this.subscriberClient = this.redisService.getPubSubClient();

      this.subscriberClient.on('error', (err) => {
        this.logger.error('Redis Subscriber Error', err.stack);
      });

      this.subscriberClient.on('connect', () => {
        this.logger.log('Redis Subscriber Connected');
        this.fileLogger.info('Redis Subscriber Connected');
      });

      await this.subscriberClient.subscribe(
        this.gatewayConfigService.wsEventsChannel,
      );

      this.subscriberClient.on('message', (channel, message) => {
        if (channel === this.gatewayConfigService.wsEventsChannel) {
          this.handleEvent(message);
        }
      });
    } catch (error) {
      this.logger.error(
        'Failed to initialize EventFanoutService — Redis may be unavailable',
        (error as Error).stack,
      );
    }
  }

  /**
   * Cancela a inscrição do canal Redis ao destruir o módulo.
   * O ciclo de vida do cliente Redis é gerenciado pelo `RedisService`.
   */
  async onModuleDestroy() {
    // Unsubscribe from channels; the client lifecycle is managed by RedisService
    if (this.subscriberClient) {
      await this.subscriberClient.unsubscribe(
        this.gatewayConfigService.wsEventsChannel,
      );
    }
  }

  /**
   * Processa uma mensagem bruta recebida do canal Redis `ws.events`.
   * Realiza parse do JSON, valida o shape e roteia para o handler apropriado.
   *
   * @param rawMessage - String JSON recebida do canal Redis
   */
  private handleEvent(rawMessage: string) {
    try {
      const parsed = JSON.parse(rawMessage) as unknown;
      if (!isRecord(parsed)) {
        this.logger.warn('Received ws.events with invalid payload shape');
        return;
      }

      const event = getString(parsed, 'event');
      if (!event) {
        this.logger.warn('Received ws.events without event name', parsed);
        return;
      }

      const payload: GatewayEvent = {
        event,
        data: parsed.data,
        tenant_id: getString(parsed, 'tenant_id') ?? undefined,
        rooms: this.extractRooms(parsed.rooms),
        version: getString(parsed, 'version') ?? undefined,
      };

      if (payload.event !== CHAT_EVENTS.ACTIVITY || this.debugChatActivity) {
        this.logger.debug(`Received ws.events: ${payload.event}`);
      }

      if (payload.rooms && payload.rooms.length > 0) {
        this.processEnvelopeEvent(payload);
        return;
      }

      if (payload.event.startsWith('ai.run.')) {
        this.processAiRunEvent(payload.event, payload.data);
        return;
      }

      if (payload.event === 'ticket.sentiment_updated') {
        this.processTicketSentimentEvent(payload.event, payload.data);
        return;
      }

      if (payload.event === 'chat.inbound_message_received') {
        this.processChatMessage(payload.data);
        return;
      }

      if (payload.event === 'notification.new') {
        this.processNotificationEvent(
          payload.event,
          payload.data,
          payload.tenant_id,
        );
        return;
      }
    } catch (error) {
      this.logger.error(
        `Failed to process ws.events message: ${(error as Error).message}`,
      );
    }
  }

  /**
   * Processa um envelope com lista explícita de rooms.
   * Valida a presença da tenant room e rejeita envios cross-tenant.
   *
   * @param payload - Envelope do evento com campo `rooms` preenchido
   */
  private processEnvelopeEvent(payload: GatewayEvent): void {
    if (!payload.tenant_id) {
      this.logger.warn('Received ws.events envelope without tenant_id', {
        event: payload.event,
      });
      return;
    }

    const rooms = payload.rooms ?? [];
    if (rooms.length === 0) {
      this.logger.warn('Received ws.events envelope without rooms', {
        event: payload.event,
        tenant_id: payload.tenant_id,
      });
      return;
    }

    const tenantRoomName = tenantRoom(payload.tenant_id);
    const hasTenantRoom = rooms.some((room) => room === tenantRoomName);
    if (!hasTenantRoom) {
      this.logger.warn('Rejecting ws.events without tenant room', {
        event: payload.event,
        tenant_id: payload.tenant_id,
        rooms,
      });
      return;
    }

    for (const room of rooms) {
      if (room.startsWith('tenant:') && room !== tenantRoomName) {
        this.logger.warn('Rejecting ws.events cross-tenant envelope', {
          event: payload.event,
          tenant_id: payload.tenant_id,
          room,
        });
        return;
      }
    }

    for (const room of rooms) {
      this.eventsGateway.emitToRoom(room, payload.event, payload.data);
    }
  }

  /**
   * Processa um evento de mensagem de chat inbound e faz fan-out para a tenant room.
   * Mapeia o payload do webhook para o formato esperado pelo frontend (`chat.message.new`).
   *
   * @param data - Payload do evento `chat.inbound_message_received`
   */
  private processChatMessage(data: unknown) {
    if (!isRecord(data)) {
      this.logger.warn('Received chat message with invalid payload', data);
      return;
    }

    const tenantId = getString(data, 'tenant_id');
    if (!tenantId) {
      this.logger.warn('Received chat message without tenant_id', data);
      return;
    }

    // Map to Frontend expected format (ChatNewMessageEvent in chat-realtime.service.ts)
    // Front expects: { ticket_id, message: { ... } }
    // The backend payload (from ChatWebhookEvent->payload) contains full normalized data

    // Check if we have message data
    const payloadRecord = getRecord(data, 'payload');
    const messageData = data.message ?? payloadRecord?.message;

    if (!messageData) {
      this.logger.warn('Could not extract message data from payload', data);
      return;
    }

    // We might not have ticket_id yet if it's a raw webhook event!
    // BUT, the goal is "inbound message received".
    // If the backend has already processed it into a Ticket/Message, it publishes a different event possibly?
    // Wait, ConsumeChatStreamCommand publishes 'chat.inbound_message_received' with 'data' = $event->payload.
    // $event->payload IS the normalized webhook payload.
    // It does NOT have ticket_id yet because it's the RAW webhook event.

    // However, the Frontend `ChatRealtimeService` expects `chat.message.new` with a `ticket_id`.
    // If we only have the raw webhook, we can't fully satisfy the frontend interface IF it strictly requires ticket_id for routing.
    // BUT the frontend service `ChatRealtimeService` joins `tenant:{id}` room.

    // Let's look at `chat-realtime.service.ts`:
    // export interface ChatNewMessageEvent { ticket_id: string; message: { ... } }

    // If we send it without ticket_id, what happens?
    // It passes it to callbacks.

    // OPTION: We are bridging the "Raw Webhook" -> "Frontend" gap for immediate feedback?
    // OR is Laravel supposed to publish a "Message Created" event AFTER processing?

    // Re-reading `ConsumeChatStreamCommand.php`:
    // It publishes `ws.events` -> `chat.inbound_message_received` with payload = normalized webhook.

    // This seems to be for "optimistic" UI or debug?
    // Actually typically we want the REAL message after established in DB.
    // But if the requirement says "receiving a webhook... not received in frontend", implies this link is missing.

    // We will emit `chat.message.new` with the data we have.
    // If `ticket_id` is missing, we might send null or try to infer.
    // For now, let's pass the whole payload as `message`.

    const outEvent = {
      ticket_id: getString(data, 'ticket_id') ?? null, // Might be null for raw webhook
      message: messageData,
    };

    const room = `tenant:${tenantId}`;

    this.eventsGateway.emitToRoom(room, 'chat.message.new', outEvent);
  }

  /**
   * Distribui um evento de execução de IA para a tenant room e, opcionalmente, para a run room.
   *
   * @param event - Nome do evento (ex: `ai.run.completed`)
   * @param data - Payload do evento com `tenant_id` e opcionalmente `run_id`
   */
  private processAiRunEvent(event: string, data: unknown): void {
    if (!isRecord(data)) {
      this.logger.warn('Received AI run event with invalid payload', data);
      return;
    }

    const tenantId = getString(data, 'tenant_id');
    if (!tenantId) {
      this.logger.warn('Received AI run event without tenant_id', data);
      return;
    }

    this.eventsGateway.emitToRoom(`tenant:${tenantId}`, event, data);

    const runId = getString(data, 'run_id');
    if (runId) {
      this.eventsGateway.emitToRoom(`run:${runId}`, event, data);
    }
  }

  /**
   * Distribui um evento de atualização de sentimento de ticket para a tenant room.
   *
   * @param event - Nome do evento (ex: `ticket.sentiment_updated`)
   * @param data - Payload do evento com `tenant_id`
   */
  private processTicketSentimentEvent(event: string, data: unknown): void {
    if (!isRecord(data)) {
      this.logger.warn(
        'Received ticket sentiment event with invalid payload',
        data,
      );
      return;
    }

    const tenantId = getString(data, 'tenant_id');
    if (!tenantId) {
      this.logger.warn(
        'Received ticket sentiment event without tenant_id',
        data,
      );
      return;
    }

    this.eventsGateway.emitToRoom(`tenant:${tenantId}`, event, data);
  }

  /**
   * Distribui um evento de nova notificação para a tenant room.
   *
   * @param event - Nome do evento (ex: `notification.new`)
   * @param data - Payload do evento com `tenant_id` e `notification`
   * @param tenantId - ID do tenant (opcional, extraído do payload se não fornecido)
   */
  private processNotificationEvent(
    event: string,
    data: unknown,
    tenantId?: string,
  ): void {
    if (!isRecord(data)) {
      this.logger.warn(
        'Received notification event with invalid payload',
        data,
      );
      return;
    }

    const resolvedTenantId = tenantId ?? getString(data, 'tenant_id');
    if (!resolvedTenantId) {
      this.logger.warn('Received notification event without tenant_id', data);
      return;
    }

    this.eventsGateway.emitToRoom(`tenant:${resolvedTenantId}`, event, data);
  }

  /**
   * Extrai e filtra uma lista de strings a partir de um valor desconhecido.
   *
   * @param value - Valor a ser inspecionado como possível array de strings
   * @returns Array de strings válidas, ou array vazio se o valor não for array
   */
  private extractRooms(value: unknown): string[] {
    if (!Array.isArray(value)) {
      return [];
    }

    return value.filter((room): room is string => typeof room === 'string');
  }
}
