import { Injectable } from '@nestjs/common';
import {
  JsonRecord,
  getBoolean,
  getRecord,
  getString,
  isRecord,
} from '../../../shared/utils/type-guards';
import { CHAT_EVENTS } from '../../../shared/constants';
import {
  PayloadSemanticMetadata,
  StreamPayload,
  FallbackStreamPayload,
} from './chat-webhook.types';

/**
 * Resolve a semantica de payloads de webhook para determinar tipos de evento (conexao, mensagem, edicao).
 *
 * Contexto: extrai e normaliza conteudo de mensagem entre formatos de diferentes provedores
 * (Uazapi, Z-API, Meta). Utilizado por ChatWebhookService e WebhookIdempotencyService para
 * identificar changeKind (new_message, edited, status, connection, presence) e compor
 * chaves de idempotencia adequadas ao tipo de evento.
 */
@Injectable()
export class PayloadSemanticsResolver {
  /**
   * Resolve os metadados semanticos de um payload de stream.
   *
   * @param payload Payload de stream a analisar
   * @returns Metadados semanticos incluindo tipo de evento e tipo de mudanca
   */
  resolvePayloadSemantics(payload: StreamPayload): PayloadSemanticMetadata {
    const fallbackEvent = this.isFallbackPayload(payload)
      ? payload.payload.event_type
      : undefined;
    const eventType = (payload.event_type ?? fallbackEvent ?? '')
      .toString()
      .toLowerCase();

    if (eventType.startsWith('connection')) {
      return {
        semanticType: 'connection',
        changeKind: 'connection',
        messageReferenceId: null,
        editEventId: null,
        eventName: CHAT_EVENTS.CHANNEL_CONNECTION,
      };
    }

    if (eventType === 'presence') {
      return {
        semanticType: 'presence',
        changeKind: 'presence',
        messageReferenceId: null,
        editEventId: null,
        eventName: CHAT_EVENTS.TYPING,
      };
    }

    if (eventType === 'messages_update') {
      return {
        semanticType: 'update',
        changeKind: 'status',
        messageReferenceId: this.resolvePrimaryStatusMessageId(payload),
        editEventId: null,
        eventName: CHAT_EVENTS.MESSAGE_STATUS,
      };
    }

    if (eventType === 'messages') {
      const editedMessageId = this.resolveEditedMessageId(payload);
      if (editedMessageId) {
        return {
          semanticType: 'update',
          changeKind: 'edited',
          messageReferenceId: editedMessageId,
          editEventId: this.resolveEditedEventId(payload),
          eventName: CHAT_EVENTS.MESSAGE_EDIT,
        };
      }

      const message = this.extractMessagePayload(payload);
      return {
        semanticType: 'create',
        changeKind: 'new_message',
        messageReferenceId: message ? (getString(message, 'id') ?? null) : null,
        editEventId: null,
        eventName: CHAT_EVENTS.MESSAGE_NEW,
      };
    }

    return {
      semanticType: 'unknown',
      changeKind: 'unknown',
      messageReferenceId: null,
      editEventId: null,
      eventName: null,
    };
  }

  /**
   * Extrai o payload de mensagem de um payload de stream.
   *
   * @param payload Payload de stream
   * @returns Record de mensagem ou null quando ausente
   */
  extractMessagePayload(payload: StreamPayload): JsonRecord | null {
    if (payload.message && isRecord(payload.message)) {
      return payload.message as JsonRecord;
    }

    if (this.isFallbackPayload(payload)) {
      const innerMsg = payload.payload?.message;
      if (innerMsg && isRecord(innerMsg)) {
        return innerMsg;
      }
    }

    if (payload.raw && isRecord(payload.raw)) {
      const rawMsg = getRecord(payload.raw, 'message');
      if (rawMsg) {
        return rawMsg;
      }
    }

    return null;
  }

  /**
   * Extrai o payload bruto (raw) de um payload de stream.
   *
   * @param payload Payload de stream
   * @returns Record do raw ou null quando ausente
   */
  extractRawPayload(payload: StreamPayload): JsonRecord | null {
    if (payload.raw && isRecord(payload.raw)) {
      return payload.raw as JsonRecord;
    }

    if (this.isFallbackPayload(payload) && payload.payload.raw) {
      const fallbackRaw = payload.payload.raw;
      if (isRecord(fallbackRaw)) {
        return fallbackRaw as JsonRecord;
      }
    }

    return null;
  }

  /**
   * Extrai o payload bruto da mensagem aninhada no raw de um payload de stream.
   *
   * @param payload Payload de stream
   * @returns Record da mensagem bruta ou null quando ausente
   */
  extractRawMessagePayload(payload: StreamPayload): JsonRecord | null {
    const rawPayload = this.extractRawPayload(payload);
    if (!rawPayload) {
      return null;
    }

    const rawMessage = getRecord(rawPayload, 'message');
    return rawMessage ?? null;
  }

  /**
   * Extrai o conteudo textual da mensagem normalizada ou do raw.
   *
   * @param message Payload de mensagem normalizado
   * @param rawMessage Payload bruto da mensagem
   * @returns Conteudo textual extraido ou string vazia
   */
  extractMessageContent(
    message: JsonRecord | null,
    rawMessage: JsonRecord | null,
  ): string {
    const rawContentRecord = rawMessage
      ? getRecord(rawMessage, 'content')
      : null;

    const normalizedContent = this.firstNonEmptyString([
      message ? getString(message, 'text') : undefined,
      message ? getString(message, 'body') : undefined,
      message ? getString(message, 'caption') : undefined,
    ]);
    if (normalizedContent) {
      return normalizedContent;
    }

    const rawContent = this.firstNonEmptyString([
      rawMessage ? getString(rawMessage, 'text') : undefined,
      rawMessage ? getString(rawMessage, 'body') : undefined,
      rawMessage ? getString(rawMessage, 'caption') : undefined,
      rawContentRecord ? getString(rawContentRecord, 'text') : undefined,
      rawContentRecord ? getString(rawContentRecord, 'caption') : undefined,
    ]);

    return rawContent ?? '';
  }

  /**
   * Resolve o discriminador para eventos de atualizacao de mensagem.
   *
   * @param payload Payload de stream
   * @returns String discriminadora para composicao da chave de idempotencia
   */
  resolveMessageUpdateDiscriminator(payload: StreamPayload): string {
    const outerRaw = payload.raw;

    const findEvent = (
      obj: Record<string, unknown> | undefined,
      depth = 0,
    ): Record<string, unknown> | undefined => {
      if (!obj || depth > 5) return undefined;

      if (
        obj.event &&
        typeof obj.event === 'object' &&
        !Array.isArray(obj.event)
      ) {
        const evt = obj.event as Record<string, unknown>;
        if (evt.MessageIDs || evt.Type) {
          return evt;
        }
      }

      if (obj.raw && typeof obj.raw === 'object') {
        return findEvent(obj.raw as Record<string, unknown>, depth + 1);
      }

      return undefined;
    };

    const event = findEvent(outerRaw as Record<string, unknown>);

    let messageIds: string[] = [];
    if (event?.MessageIDs && Array.isArray(event.MessageIDs)) {
      messageIds = event.MessageIDs as string[];
    }

    const statusType = (event?.Type as string) ?? 'unknown';

    if (messageIds.length > 0) {
      return `${messageIds.join(',')}_${statusType}`;
    }

    const timestamp = event?.Timestamp;
    if (
      timestamp !== undefined &&
      timestamp !== null &&
      (typeof timestamp === 'string' || typeof timestamp === 'number')
    ) {
      return `ts_${timestamp}_${statusType}`;
    }

    return 'generic';
  }

  /**
   * Resolve o ID da mensagem editada a partir do payload.
   *
   * @param payload Payload de stream do evento de edicao
   * @returns ID da mensagem editada ou null quando nao identificado
   */
  resolveEditedMessageId(payload: StreamPayload): string | null {
    const message = this.extractMessagePayload(payload);
    const messageEdited = message ? getString(message, 'edited') : undefined;
    if (messageEdited && messageEdited.trim() !== '') {
      return messageEdited;
    }

    const rawMessage = this.extractRawMessagePayload(payload);
    const rawEdited = rawMessage ? getString(rawMessage, 'edited') : undefined;
    if (rawEdited && rawEdited.trim() !== '') {
      return rawEdited;
    }

    const rawPayload = this.extractRawPayload(payload);
    const isZapiEdit = rawPayload
      ? getBoolean(rawPayload, 'isEdit') === true
      : false;

    if (!isZapiEdit) {
      return null;
    }

    return (
      (rawMessage ? getString(rawMessage, 'messageid') : undefined) ??
      (rawMessage ? getString(rawMessage, 'id') : undefined) ??
      (rawPayload ? getString(rawPayload, 'messageId') : undefined) ??
      (rawPayload ? getString(rawPayload, 'id') : undefined) ??
      (message ? getString(message, 'id') : undefined) ??
      null
    );
  }

  /**
   * Resolve o ID do evento de edicao a partir do payload.
   *
   * @param payload Payload de stream do evento de edicao
   * @returns ID do evento editado ou null quando nao identificado
   */
  resolveEditedEventId(payload: StreamPayload): string | null {
    const message = this.extractMessagePayload(payload);
    const rawMessage = this.extractRawMessagePayload(payload);
    const rawPayload = this.extractRawPayload(payload);

    const candidates = [
      message ? getString(message, 'id') : undefined,
      rawMessage ? getString(rawMessage, 'id') : undefined,
      rawMessage ? getString(rawMessage, 'messageid') : undefined,
      rawMessage ? getString(rawMessage, 'messageId') : undefined,
      rawPayload ? getString(rawPayload, 'messageId') : undefined,
      rawPayload ? getString(rawPayload, 'id') : undefined,
      this.isFallbackPayload(payload) ? payload.payload.message?.id : undefined,
    ];

    for (const candidate of candidates) {
      if (typeof candidate === 'string' && candidate.trim() !== '') {
        return candidate;
      }
    }

    return null;
  }

  /**
   * Resolve o ID primario de mensagem de status a partir do evento aninhado.
   *
   * @param payload Payload de stream de evento messages_update
   * @returns ID da primeira mensagem ou null quando ausente
   */
  private resolvePrimaryStatusMessageId(payload: StreamPayload): string | null {
    const outerRaw = payload.raw;
    const innerRaw = outerRaw?.raw as Record<string, unknown> | undefined;
    const event = (innerRaw?.event ?? outerRaw?.event) as
      | Record<string, unknown>
      | undefined;
    const messageIds = (event?.MessageIDs ?? []) as string[];

    if (messageIds.length > 0) {
      return messageIds[0] ?? null;
    }

    return payload.message?.id ?? null;
  }

  /**
   * Retorna o primeiro valor nao vazio do array de candidatos.
   *
   * @param values Array de candidatos a string nao vazia
   * @returns Primeiro valor nao vazio ou null
   */
  private firstNonEmptyString(
    values: Array<string | undefined>,
  ): string | null {
    for (const value of values) {
      if (typeof value === 'string' && value.trim() !== '') {
        return value;
      }
    }

    return null;
  }

  /**
   * Verifica se o payload e do tipo FallbackStreamPayload.
   *
   * @param payload Payload de stream a verificar
   * @returns true quando o payload e do tipo fallback
   */
  private isFallbackPayload(
    payload: StreamPayload,
  ): payload is FallbackStreamPayload {
    return (payload as FallbackStreamPayload).payload !== undefined;
  }
}
