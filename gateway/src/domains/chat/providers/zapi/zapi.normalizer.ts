import { Injectable, Logger } from '@nestjs/common';
import { sha256 } from '../../../../shared/utils/hash.util';
import {
  NormalizedWebhookEvent,
  MessagePayload,
} from '../../contracts/provider.interface';
import { ZapiWebhookPayload } from '../../models/zapi.model';

export type { ZapiWebhookPayload };

/**
 * Normaliza payloads de webhook da Z-API para o contrato interno do gateway.
 */
@Injectable()
export class ZapiNormalizer {
  private readonly logger = new Logger(ZapiNormalizer.name);

  /**
   * Converte um payload bruto da Z-API em evento normalizado do dominio.
   */
  normalize(
    webhookToken: string,
    payload: ZapiWebhookPayload,
    tenantId: string,
    instanceId: string,
  ): NormalizedWebhookEvent {
    const eventType = this.resolveEventType(payload);
    const direction = this.resolveDirection(payload, eventType);

    const normalized: NormalizedWebhookEvent = {
      tenantId,
      instanceId,
      instanceWebhookToken: webhookToken,
      provider: 'zapi',
      eventType,
      direction,
      rawPayload: payload as unknown as Record<string, unknown>,
      idempotencyKey: this.generateIdempotencyKey(webhookToken, payload),
      receivedAt: new Date(),
    };

    if (direction === 'inbound' || direction === 'outbound') {
      normalized.message = this.extractMessage(payload);
    }

    if (direction === 'status') {
      normalized.status = this.extractStatus(payload);
    }

    if (direction === 'connection') {
      normalized.connection = this.extractConnection(payload);
    }

    return normalized;
  }

  /**
   * Resolve o tipo do evento a partir do payload bruto da Z-API.
   *
   * @param payload - Payload bruto recebido da Z-API
   * @returns String canonica do tipo de evento
   */
  private resolveEventType(payload: ZapiWebhookPayload): string {
    if (payload.type) {
      switch (payload.type) {
        case 'MessageStatusCallback':
          return 'message_status';
        case 'ReceivedCallback':
          return 'message_received';
        case 'DisconnectedCallback':
          return 'disconnected';
        case 'ConnectedCallback':
          return 'connected';
      }
    }

    if (
      payload.connected !== undefined ||
      payload.smartphoneConnected !== undefined
    ) {
      return payload.connected ? 'connected' : 'disconnected';
    }

    if (payload.ids && payload.status) {
      return 'message_status';
    }

    if (payload.messageId) {
      return 'message';
    }

    return 'unknown';
  }

  /**
   * Determina a direcao do evento com base no tipo e flag fromMe.
   *
   * @param payload - Payload bruto recebido da Z-API
   * @param eventType - Tipo do evento ja resolvido
   * @returns Direcao normalizada do evento
   */
  private resolveDirection(
    payload: ZapiWebhookPayload,
    eventType: string,
  ): 'inbound' | 'outbound' | 'status' | 'connection' {
    if (eventType === 'connected' || eventType === 'disconnected') {
      return 'connection';
    }

    if (eventType === 'message_status') {
      return 'status';
    }

    if (payload.fromMe) {
      return 'outbound';
    }

    return 'inbound';
  }

  /**
   * Extrai o payload de mensagem normalizado a partir do payload bruto.
   *
   * @param payload - Payload bruto recebido da Z-API
   * @returns Mensagem normalizada ou undefined quando ausente
   */
  private extractMessage(
    payload: ZapiWebhookPayload,
  ): MessagePayload | undefined {
    if (!payload.messageId) {
      return undefined;
    }

    const type = this.resolveMessageType(payload);
    const { text, mediaUrl, caption, mimeType, fileName } =
      this.extractContent(payload);

    return {
      id: payload.messageId,
      from: payload.phone ?? '',
      to: payload.connectedPhone ?? '',
      type,
      text,
      caption,
      mediaUrl,
      mimeType,
      fileName,
      timestamp: new Date(),
      isFromMe: payload.fromMe ?? false,
      isGroup: payload.isGroup ?? false,
      quotedMessageId: undefined, // Z-API doesn't provide this in standard payload
      senderPhoto: payload.senderPhoto ?? payload.photo ?? undefined,
    };
  }

  /**
   * Determina o tipo de conteudo da mensagem a partir do payload.
   *
   * @param payload - Payload bruto recebido da Z-API
   * @returns Tipo de mensagem normalizado
   */
  private resolveMessageType(
    payload: ZapiWebhookPayload,
  ): MessagePayload['type'] {
    if (payload.text) return 'text';
    if (payload.image) return 'image';
    if (payload.video) return 'video';
    if (payload.audio) return 'audio';
    if (payload.document) return 'document';
    if (payload.sticker) return 'sticker';
    if (payload.location) return 'location';
    if (payload.contact) return 'contact';
    return 'text';
  }

  /**
   * Extrai texto, URL de midia, legenda e metadados do payload da Z-API.
   *
   * @param payload - Payload bruto recebido da Z-API
   * @returns Objeto com campos de conteudo extraidos
   */
  private extractContent(payload: ZapiWebhookPayload): {
    text?: string;
    mediaUrl?: string;
    caption?: string;
    mimeType?: string;
    fileName?: string;
  } {
    if (payload.text) {
      return { text: payload.text.message };
    }

    if (payload.image) {
      return {
        mediaUrl: payload.image.imageUrl,
        caption: payload.image.caption,
        mimeType: payload.image.mimeType,
      };
    }

    if (payload.video) {
      return {
        mediaUrl: payload.video.videoUrl,
        caption: payload.video.caption,
        mimeType: payload.video.mimeType,
      };
    }

    if (payload.audio) {
      return {
        mediaUrl: payload.audio.audioUrl,
        mimeType: payload.audio.mimeType,
      };
    }

    if (payload.document) {
      return {
        mediaUrl: payload.document.documentUrl,
        caption: payload.document.caption,
        mimeType: payload.document.mimeType,
        fileName: payload.document.fileName,
      };
    }

    return {};
  }

  /**
   * Extrai informacoes de status de entrega do payload.
   *
   * @param payload - Payload bruto recebido da Z-API
   * @returns Status normalizado ou undefined quando ausente
   */
  private extractStatus(
    payload: ZapiWebhookPayload,
  ): NormalizedWebhookEvent['status'] {
    if (!payload.ids || payload.ids.length === 0) {
      return undefined;
    }

    const statusMap: Record<string, 'sent' | 'delivered' | 'read' | 'failed'> =
      {
        PENDING: 'sent',
        SENT: 'sent',
        RECEIVED: 'delivered',
        READ: 'read',
        PLAYED: 'read',
        ERROR: 'failed',
      };

    return {
      messageId: payload.ids[0],
      status: statusMap[payload.status ?? 'PENDING'] ?? 'sent',
      timestamp: new Date(),
    };
  }

  /**
   * Extrai informacoes de conexao do payload.
   *
   * @param payload - Payload bruto recebido da Z-API
   * @returns Dados de conexao normalizados
   */
  private extractConnection(
    payload: ZapiWebhookPayload,
  ): NormalizedWebhookEvent['connection'] {
    return {
      status: payload.connected ? 'connected' : 'disconnected',
      connected: payload.connected ?? false,
    };
  }

  /**
   * Gera chave de idempotencia unica para o evento recebido.
   *
   * @param token - Token do webhook da instancia
   * @param payload - Payload bruto recebido da Z-API
   * @returns Chave de idempotencia com no maximo 255 caracteres
   */
  private generateIdempotencyKey(
    token: string,
    payload: ZapiWebhookPayload,
  ): string {
    const parts = [
      'zapi',
      token,
      payload.messageId ?? payload.ids?.[0] ?? '',
      payload.status ?? payload.type ?? 'unknown',
    ].filter(Boolean);

    const raw = parts.join(':');
    if (raw.length <= 255) {
      return `idempo:${raw}`;
    }

    return `idempo_hash:${sha256(raw)}`;
  }
}
