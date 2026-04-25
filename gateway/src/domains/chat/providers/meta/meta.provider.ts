import { Injectable, Logger } from '@nestjs/common';
import {
  MetaWebhookPayload,
  MessagePayload,
} from '../../contracts/meta-provider.interface';

/**
 * Tipo para mensagem individual do webhook da Meta.
 */
type MetaWebhookMessage = NonNullable<
  MetaWebhookPayload['entry'][0]['changes'][0]['value']['messages']
>[0];

/**
 * Resultado da normalizacao de um webhook da Meta.
 */
export interface NormalizedMetaEvent {
  provider: 'meta';
  event_type: string;
  direction: 'inbound' | 'outbound' | 'status' | 'connection';
  phone_number_id: string;
  display_phone_number: string;
  message?: MessagePayload;
  status?: {
    messageId: string;
    status: string;
    timestamp: Date;
  };
  raw: MetaWebhookPayload;
}

/**
 * Normalizador de payloads de webhook da Meta WhatsApp Business API.
 * Converte payloads brutos da API Graph para o formato interno do gateway.
 */
@Injectable()
export class MetaProvider {
  private readonly logger = new Logger(MetaProvider.name);

  /**
   * Normaliza um payload bruto do webhook da Meta.
   *
   * @param payload - Payload bruto recebido da Meta API
   * @returns Evento normalizado no formato interno
   */
  normalize(payload: MetaWebhookPayload): NormalizedMetaEvent {
    const entry = payload.entry[0];
    const change = entry?.changes?.[0];
    const value = change?.value;
    const field = change?.field ?? 'messages';

    const metadata = value?.metadata;
    const phoneNumberId = metadata?.phone_number_id ?? '';
    const displayPhoneNumber = metadata?.display_phone_number ?? '';

    // Determine event type and direction
    const eventType = this.resolveEventType(value, field);
    const direction = this.resolveDirection(value, eventType);

    const normalized: NormalizedMetaEvent = {
      provider: 'meta',
      event_type: eventType,
      direction,
      phone_number_id: phoneNumberId,
      display_phone_number: displayPhoneNumber,
      raw: payload,
    };

    // Extract message if present
    if (direction === 'inbound' || direction === 'outbound') {
      const message = this.extractMessage(value, displayPhoneNumber);
      if (message) {
        normalized.message = message;
      }
    }

    // Extract status if present
    if (direction === 'status') {
      const status = this.extractStatus(value);
      if (status) {
        normalized.status = status;
      }
    }

    return normalized;
  }

  /**
   * Resolve o tipo do evento a partir do payload e field.
   */
  private resolveEventType(
    value: MetaWebhookPayload['entry'][0]['changes'][0]['value'],
    field: string,
  ): string {
    // Check if it's a status update
    if (value?.statuses && value.statuses.length > 0) {
      return 'message_status';
    }

    // Check if it's a message
    if (value?.messages && value.messages.length > 0) {
      return 'message_received';
    }

    // Fallback to field value
    return field;
  }

  /**
   * Determina a direcao do evento.
   */
  private resolveDirection(
    value: MetaWebhookPayload['entry'][0]['changes'][0]['value'],
    eventType: string,
  ): 'inbound' | 'outbound' | 'status' | 'connection' {
    if (eventType === 'message_status') {
      return 'status';
    }

    if (value?.messages && value.messages.length > 0) {
      // Messages from the business to the user are outbound
      // Messages from the user to the business are inbound
      // For now, we treat all received messages as inbound
      return 'inbound';
    }

    return 'inbound';
  }

  /**
   * Extrai mensagem normalizada do payload.
   */
  private extractMessage(
    value: MetaWebhookPayload['entry'][0]['changes'][0]['value'],
    displayPhoneNumber: string,
  ): MessagePayload | undefined {
    const msg = value?.messages?.[0];
    if (!msg) {
      return undefined;
    }

    const type = this.resolveMessageType(msg);

    return {
      id: msg.id,
      from: msg.from,
      to: displayPhoneNumber,
      type,
      text: msg.text?.body,
      timestamp: new Date(parseInt(msg.timestamp) * 1000),
      isFromMe: false, // Meta doesn't provide this directly
      isGroup: false,
      mimeType: this.extractMimeType(msg),
    };
  }

  /**
   * Extrai status normalizado do payload.
   */
  private extractStatus(
    value: MetaWebhookPayload['entry'][0]['changes'][0]['value'],
  ): NormalizedMetaEvent['status'] | undefined {
    const statusUpdate = value?.statuses?.[0];
    if (!statusUpdate) {
      return undefined;
    }

    return {
      messageId: statusUpdate.id,
      status: statusUpdate.status,
      timestamp: new Date(parseInt(statusUpdate.timestamp) * 1000),
    };
  }

  /**
   * Resolve o tipo de mensagem a partir do payload.
   */
  private resolveMessageType(msg: MetaWebhookMessage): MessagePayload['type'] {
    if (msg.text) return 'text';
    if (msg.image) return 'image';
    if (msg.video) return 'video';
    if (msg.audio) return 'audio';
    if (msg.document) return 'document';
    if (msg.location) return 'location';
    if (msg.contacts) return 'contact';
    return 'text';
  }

  /**
   * Extrai MIME type da mensagem.
   */
  private extractMimeType(msg: MetaWebhookMessage): string | undefined {
    if (msg.image) return msg.image.mime_type;
    if (msg.video) return msg.video.mime_type;
    if (msg.audio) return msg.audio.mime_type;
    if (msg.document) return msg.document.mime_type;
    return undefined;
  }
}
