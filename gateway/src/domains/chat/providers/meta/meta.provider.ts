import { Injectable, Logger } from '@nestjs/common';
import {
  MetaWebhookPayload,
  MessagePayload,
} from '../../contracts/meta-provider.interface';
import {
  MessageDeliveryError,
  MessageWindow,
} from '../../contracts/provider.interface';

/**
 * Tipo para mensagem individual do webhook da Meta.
 */
type MetaWebhookMessage = NonNullable<
  MetaWebhookPayload['entry'][0]['changes'][0]['value']['messages']
>[0];

/**
 * Tipo para o `value` de um `change` do webhook da Meta.
 */
type MetaWebhookValue = MetaWebhookPayload['entry'][0]['changes'][0]['value'];

/**
 * Tipo para status individual do webhook da Meta.
 */
type MetaWebhookStatus = NonNullable<MetaWebhookValue['statuses']>[0];

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
    /** Janela de atendimento capturada de `conversation` (apenas em eventos de status). */
    window?: MessageWindow;
    /** Erros reportados pela Meta quando `status === 'failed'`. */
    errors?: MessageDeliveryError[];
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
   * Normaliza um payload bruto do webhook da Meta, produzindo UM evento por
   * mensagem/status presente no lote (`entry[] -> changes[] -> messages[]|statuses[]`).
   *
   * A Meta agrupa múltiplos eventos em um único POST — processar apenas o
   * primeiro elemento (como o antigo `normalize()` fazia) descarta mensagens
   * silenciosamente.
   *
   * @param payload - Payload bruto recebido da Meta API
   * @returns Lista de eventos normalizados, um por mensagem/status/template
   */
  normalizeAll(payload: MetaWebhookPayload): NormalizedMetaEvent[] {
    const events: NormalizedMetaEvent[] = [];

    for (const entry of payload.entry ?? []) {
      for (const change of entry.changes ?? []) {
        const field = change.field ?? 'messages';
        const value = change.value;

        if (field === 'message_template_status_update') {
          events.push({
            provider: 'meta',
            event_type: 'meta.template.status_updated',
            direction: 'template_status',
            phone_number_id: '',
            display_phone_number: '',
            template: this.extractTemplate(value),
            raw: payload,
          });
          continue;
        }

        const metadata = value?.metadata;
        const phoneNumberId = metadata?.phone_number_id ?? '';
        const displayPhoneNumber = metadata?.display_phone_number ?? '';

        const messages = value?.messages ?? [];
        const statuses = value?.statuses ?? [];

        if (messages.length === 0 && statuses.length === 0) {
          // Ramo de compatibilidade: mudanca sem mensagens/status conhecidos
          // (ex.: alertas de conta, qualidade do numero). Preserva o
          // comportamento historico do normalize() de nao descartar o evento.
          events.push({
            provider: 'meta',
            event_type: field,
            direction: 'inbound',
            phone_number_id: phoneNumberId,
            display_phone_number: displayPhoneNumber,
            raw: payload,
          });
          continue;
        }

        for (const msg of messages) {
          events.push({
            provider: 'meta',
            event_type: 'message_received',
            direction: 'inbound',
            phone_number_id: phoneNumberId,
            display_phone_number: displayPhoneNumber,
            message: this.buildMessagePayload(msg, displayPhoneNumber),
            raw: payload,
          });
        }

        for (const status of statuses) {
          events.push({
            provider: 'meta',
            event_type: 'message_status',
            direction: 'status',
            phone_number_id: phoneNumberId,
            display_phone_number: displayPhoneNumber,
            status: this.buildStatusPayload(status),
            raw: payload,
          });
        }
      }
    }

    if (events.length === 0) {
      // Payload vazio/malformado (sem entry/changes) — mantem o formato de
      // fallback do normalize() original para nao quebrar chamadores.
      events.push({
        provider: 'meta',
        event_type: 'messages',
        direction: 'inbound',
        phone_number_id: '',
        display_phone_number: '',
        raw: payload,
      });
    }

    return events;
  }

  /**
   * Normaliza um payload bruto do webhook da Meta.
   * Preservado por compatibilidade — delega para o primeiro evento de
   * `normalizeAll()`. Especificações existentes (`meta.provider.spec.ts`)
   * dependem deste método processando apenas o primeiro elemento do lote.
   *
   * @param payload - Payload bruto recebido da Meta API
   * @returns Primeiro evento normalizado no formato interno
   */
  normalize(payload: MetaWebhookPayload): NormalizedMetaEvent {
    const [first] = this.normalizeAll(payload);
    return first;
  }

  /**
   * Constroi o payload de mensagem normalizado a partir da mensagem bruta da Meta.
   */
  private buildMessagePayload(
    msg: MetaWebhookMessage,
    displayPhoneNumber: string,
  ): MessagePayload {
    const type = this.resolveMessageType(msg);

    return {
      id: msg.id,
      from: msg.from,
      to: displayPhoneNumber,
      type,
      text: msg.text?.body,
      timestamp: new Date(parseInt(msg.timestamp, 10) * 1000),
      isFromMe: false, // A Meta nao fornece este campo diretamente
      isGroup: false,
      mimeType: this.extractMimeType(msg),
      quotedMessageId: msg.context?.id,
      referral: msg.referral
        ? {
            source_id: msg.referral.source_id,
            source_type: msg.referral.source_type,
            headline: msg.referral.headline,
            ctwa_clid: msg.referral.ctwa_clid,
          }
        : undefined,
    };
  }

  /**
   * Constroi o status normalizado a partir do status bruto da Meta, capturando
   * a janela de atendimento (`conversation.expiration_timestamp`/`origin.type`)
   * e os erros de entrega (`errors[]`) quando presentes.
   */
  private buildStatusPayload(
    status: MetaWebhookStatus,
  ): NormalizedMetaEvent['status'] {
    return {
      messageId: status.id,
      status: status.status,
      timestamp: new Date(parseInt(status.timestamp, 10) * 1000),
      window: this.resolveWindowFromConversation(status),
      errors: this.resolveErrors(status),
    };
  }

  /**
   * Resolve a janela de atendimento a partir de `conversation.expiration_timestamp`.
   * Tipo `72h` quando `conversation.origin.type === 'referral_conversion'` (CTWA),
   * senao `24h`.
   */
  private resolveWindowFromConversation(
    status: MetaWebhookStatus,
  ): MessageWindow | undefined {
    const expirationTimestamp = status.conversation?.expiration_timestamp;
    if (!expirationTimestamp) {
      return undefined;
    }

    const expiresAtSeconds = parseInt(expirationTimestamp, 10);
    if (!Number.isFinite(expiresAtSeconds)) {
      return undefined;
    }

    const type: MessageWindow['type'] =
      status.conversation?.origin?.type === 'referral_conversion'
        ? '72h'
        : '24h';

    return {
      expiresAt: new Date(expiresAtSeconds * 1000),
      type,
    };
  }

  /**
   * Extrai a lista de erros de entrega do status, achatando `error_data.details`.
   */
  private resolveErrors(
    status: MetaWebhookStatus,
  ): MessageDeliveryError[] | undefined {
    if (!status.errors || status.errors.length === 0) {
      return undefined;
    }

    return status.errors.map((error) => ({
      code: error.code,
      title: error.title,
      message: error.message,
      details: error.error_data?.details,
    }));
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
    if (msg.sticker) return 'sticker';
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
    if (msg.sticker) return msg.sticker.mime_type;
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
