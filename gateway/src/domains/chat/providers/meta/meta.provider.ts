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
 * Payload normalizado para eventos de mudança de status de template (Meta).
 * Campos baseados em https://developers.facebook.com/docs/whatsapp/embedded-signup/webhooks#message-template-status-update
 */
export interface NormalizedMetaTemplateStatus {
  external_id: string;
  name: string;
  language: string;
  event: string;
  status: 'approved' | 'rejected' | 'pending' | 'disabled' | 'unknown';
  reason: string | null;
  mmlite_status: string | null;
}

/**
 * Resultado da normalizacao de um webhook da Meta.
 */
export interface NormalizedMetaEvent {
  provider: 'meta';
  event_type: string;
  direction:
    | 'inbound'
    | 'outbound'
    | 'status'
    | 'connection'
    | 'template_status';
  phone_number_id: string;
  display_phone_number: string;
  message?: MessagePayload;
  status?: {
    messageId: string;
    status: string;
    timestamp: Date;
  };
  template?: NormalizedMetaTemplateStatus;
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

    // Branch: message_template_status_update — não tem metadata.phone_number_id
    if (field === 'message_template_status_update') {
      const template = this.extractTemplate(value);
      return {
        provider: 'meta',
        event_type: 'meta.template.status_updated',
        direction: 'template_status',
        phone_number_id: '',
        display_phone_number: '',
        template,
        raw: payload,
      };
    }

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

  /**
   * Extrai payload normalizado de evento `message_template_status_update`.
   * O `value` desse evento NÃO segue o shape padrão (sem `metadata`); leitura
   * defensiva via runtime checks.
   */
  private extractTemplate(value: unknown): NormalizedMetaTemplateStatus {
    const v = (value ?? {}) as Record<string, unknown>;

    const externalId =
      typeof v.message_template_id === 'string'
        ? v.message_template_id
        : typeof v.message_template_id === 'number'
          ? String(v.message_template_id)
          : '';
    const name =
      typeof v.message_template_name === 'string'
        ? v.message_template_name
        : '';
    const language =
      typeof v.message_template_language === 'string'
        ? v.message_template_language
        : '';
    const event = typeof v.event === 'string' ? v.event : '';
    const reason =
      typeof v.reason === 'string' && v.reason.length > 0 ? v.reason : null;
    const mmliteStatus =
      typeof v.mmlite_status === 'string' && v.mmlite_status.length > 0
        ? v.mmlite_status
        : null;

    return {
      external_id: externalId,
      name,
      language,
      event,
      status: this.mapTemplateStatus(event),
      reason,
      mmlite_status: mmliteStatus,
    };
  }

  /**
   * Mapeia o `event` da Meta para o status normalizado.
   */
  private mapTemplateStatus(
    event: string,
  ): NormalizedMetaTemplateStatus['status'] {
    switch (event.toUpperCase()) {
      case 'APPROVED':
        return 'approved';
      case 'REJECTED':
        return 'rejected';
      case 'PENDING':
      case 'IN_APPEAL':
      case 'PENDING_DELETION':
        return 'pending';
      case 'DISABLED':
      case 'PAUSED':
      case 'FLAGGED':
        return 'disabled';
      default:
        return 'unknown';
    }
  }
}
