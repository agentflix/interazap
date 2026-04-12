import { Injectable, Logger } from '@nestjs/common';
import {
  JsonRecord,
  cloneRecord,
  getBoolean,
  getRecord,
  getString,
  isRecord,
} from '../../../shared/utils/type-guards';
import {
  FallbackStreamPayload,
  PayloadSemanticMetadata,
  StreamPayload,
  ZapiStreamPayload,
} from './chat-webhook.types';
import { PayloadSemanticsResolver } from './payload-semantics-resolver.service';
import { ZapiAdapter } from '../providers/zapi/zapi.adapter';
import { NormalizedWebhookEvent } from '../contracts/provider.interface';

/**
 * Normaliza, extrai e transforma payloads de webhook de diferentes provedores
 * em formatos internos consistentes para processamento downstream.
 */
@Injectable()
export class ChatWebhookEventNormalizer {
  private readonly logger = new Logger(ChatWebhookEventNormalizer.name);

  constructor(readonly payloadSemanticsResolver: PayloadSemanticsResolver) {}

  /**
   * Verifica se o payload e do tipo FallbackStreamPayload.
   *
   * @param payload - Payload de stream a verificar
   * @returns true quando o payload tem estrutura de fallback
   */
  isFallbackPayload(payload: StreamPayload): payload is FallbackStreamPayload {
    return (payload as FallbackStreamPayload).payload !== undefined;
  }

  /**
   * Extrai o objeto de mensagem do payload normalizado.
   */
  extractMessagePayload(payload: StreamPayload): JsonRecord | null {
    // Direct message field (Zapi / Uazapi normalized)
    if (payload.message && isRecord(payload.message)) {
      return payload.message as JsonRecord;
    }

    // Fallback payload structure
    if (this.isFallbackPayload(payload)) {
      const innerMsg = payload.payload?.message;
      if (innerMsg && isRecord(innerMsg)) {
        return innerMsg;
      }
    }

    // Raw nested message
    if (payload.raw && isRecord(payload.raw)) {
      const rawMsg = getRecord(payload.raw, 'message');
      if (rawMsg) {
        return rawMsg;
      }
    }

    return null;
  }

  /**
   * Extrai o payload bruto (raw) do stream payload.
   *
   * @param payload - Payload de stream
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
   * Extrai o payload bruto da mensagem aninhada no raw do stream payload.
   *
   * @param payload - Payload de stream
   * @returns Record da mensagem raw ou null quando ausente
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
   * Extrai o objeto de conexao do raw do payload.
   *
   * @param payload - Payload de stream
   * @returns Record de conexao ou objeto vazio
   */
  extractConnection(payload: StreamPayload): JsonRecord {
    if (payload.raw && isRecord(payload.raw)) {
      const instance = getRecord(payload.raw, 'instance');
      if (instance) {
        return instance;
      }
    }

    if (this.isFallbackPayload(payload) && payload.payload.raw) {
      const raw = payload.payload.raw;
      if (isRecord(raw)) {
        const instance = getRecord(raw, 'instance');
        if (instance) {
          return instance;
        }
      }
    }

    return {};
  }

  /**
   * Extrai o objeto de status do raw do payload.
   *
   * @param payload - Payload de stream
   * @returns Record de status ou objeto vazio
   */
  extractStatusPayload(payload: StreamPayload): JsonRecord {
    if (payload.raw && isRecord(payload.raw)) {
      const status = getRecord(payload.raw, 'status');
      if (status) {
        return status;
      }
    }

    if (this.isFallbackPayload(payload) && payload.payload.raw) {
      const raw = payload.payload.raw;
      if (isRecord(raw)) {
        const status = getRecord(raw, 'status');
        if (status) {
          return status;
        }
      }
    }

    return {};
  }

  /**
   * Resolve o ID da mensagem editada a partir do campo 'edited' ou flag isEdit.
   *
   * @param payload - Payload de stream do evento de edicao
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
   * Resolve o ID do evento de edicao a partir de candidatos no payload.
   *
   * @param payload - Payload de stream do evento de edicao
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
   * Extrai o conteudo textual da mensagem normalizada ou do raw.
   *
   * @param message - Payload de mensagem normalizado
   * @param rawMessage - Payload bruto da mensagem
   * @returns Texto da mensagem ou string vazia
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
   * Retorna o primeiro valor nao vazio do array de strings.
   *
   * @param values - Candidatos a string nao vazia
   * @returns Primeiro valor nao vazio ou null
   */
  firstNonEmptyString(values: Array<string | undefined>): string | null {
    for (const value of values) {
      if (typeof value === 'string' && value.trim() !== '') {
        return value;
      }
    }

    return null;
  }

  /**
   * Normaliza o tipo de status textual para o formato interno canonico.
   *
   * @param statusType - Tipo de status como string do provedor
   * @returns Status normalizado ou null quando desconhecido
   */
  normalizeMessageStatus(
    statusType: string,
  ): 'sent' | 'delivered' | 'read' | null {
    const lower = statusType.toLowerCase();
    if (lower === 'delivered' || lower === 'delivery_ack') {
      return 'delivered';
    }
    if (lower === 'read' || lower === 'played' || lower === 'viewed') {
      return 'read';
    }
    if (lower === 'sent' || lower === 'server_ack') {
      return 'sent';
    }
    return null;
  }

  /**
   * Extrai o status de conexao do payload normalizado.
   *
   * @param payload - Payload de stream
   * @returns Status de conexao ou undefined quando ausente
   */
  resolveConnectionStatus(payload: StreamPayload): string | undefined {
    const connection = this.extractConnection(payload);
    const connectionStatus = getString(connection, 'status');
    if (connectionStatus) {
      return connectionStatus;
    }

    const statusPayload = this.extractStatusPayload(payload);
    const statusValue = getString(statusPayload, 'status');
    if (statusValue) {
      return statusValue;
    }

    const connectedFlag = getBoolean(statusPayload, 'connected');
    if (typeof connectedFlag === 'boolean') {
      return connectedFlag ? 'connected' : 'disconnected';
    }

    const loggedInFlag = getBoolean(statusPayload, 'loggedIn');
    if (typeof loggedInFlag === 'boolean') {
      return loggedInFlag ? 'connected' : 'disconnected';
    }

    return undefined;
  }

  /**
   * Delega resolucao de semantica do payload para o servico especializado.
   *
   * @param payload - Payload de stream
   * @returns Metadados semanticos do payload
   */
  resolvePayloadSemantics(payload: StreamPayload): PayloadSemanticMetadata {
    return this.payloadSemanticsResolver.resolvePayloadSemantics(payload);
  }

  /**
   * Mapeia o evento normalizado da Z-API para o formato de stream interno.
   *
   * @param normalized - Evento normalizado pelo ZapiAdapter
   * @returns Payload de stream no formato Z-API
   */
  mapZapiNormalizedToStream(
    normalized: NormalizedWebhookEvent,
  ): ZapiStreamPayload {
    const directionMap: Record<typeof normalized.direction, string> = {
      inbound: 'incoming',
      outbound: 'outgoing',
      status: 'status',
      connection: 'connection',
    };

    const eventType =
      normalized.direction === 'connection'
        ? 'connection'
        : normalized.eventType === 'message'
          ? 'messages'
          : normalized.eventType;

    const basePayload: ZapiStreamPayload = {
      provider: 'zapi',
      event_type: eventType,
      direction: directionMap[normalized.direction],
      instance_webhook_token: normalized.instanceWebhookToken,
      tenant_id: normalized.tenantId,
      instance_id: normalized.instanceId,
      raw: normalized.rawPayload,
    };

    if (normalized.message) {
      basePayload.message = {
        id: normalized.message.id,
        from: normalized.message.from,
        to: normalized.message.to,
        type: normalized.message.type,
        text: normalized.message.text,
        body: normalized.message.text,
        caption: normalized.message.caption,
        mediaUrl: normalized.message.mediaUrl,
        mimeType: normalized.message.mimeType,
        fileName: normalized.message.fileName,
        timestamp: normalized.message.timestamp,
        fromMe: normalized.message.isFromMe,
        isGroup: normalized.message.isGroup,
        senderPhoto: normalized.message.senderPhoto,
      };
    }

    if (normalized.status) {
      basePayload.status = {
        messageId: normalized.status.messageId,
        status: normalized.status.status,
        timestamp: normalized.status.timestamp,
        error: normalized.status.error,
      };

      if (!basePayload.message && normalized.status.messageId) {
        basePayload.message = {
          id: normalized.status.messageId,
          status: normalized.status.status,
        };
      }
    }

    if (normalized.connection) {
      basePayload.connection = {
        status: normalized.connection.status,
        connected: normalized.connection.connected,
      };
      basePayload.status = {
        status: normalized.connection.status,
        connected: normalized.connection.connected,
      };
    }

    return basePayload;
  }

  /**
   * Converte o payload de stream em record para publicacao no Redis Stream.
   *
   * @param payload - Payload de stream a converter
   * @returns Record serializavel para publicacao
   */
  toStreamRecord(payload: StreamPayload): Record<string, unknown> {
    const record = cloneRecord(payload);
    const semantics = this.resolvePayloadSemantics(payload);
    record.instance_id = payload.instance_id ?? null;
    record.semantic_type = semantics.semanticType;
    record.change_kind = semantics.changeKind;
    record.event_name = semantics.eventName;
    record.message_reference_id = semantics.messageReferenceId;

    if (record.message && isRecord(record.message)) {
      const photo = getString(record.message, 'senderPhoto');
      if (photo) {
        record.profile_picture_url = photo;
      }
    }

    if (semantics.changeKind === 'edited') {
      const originalEventType =
        typeof record.event_type === 'string' ? record.event_type : null;
      record.event_type = 'message.edit';
      record.source_event_type = originalEventType;
      record.message_id = semantics.messageReferenceId;

      if (record.message && isRecord(record.message)) {
        const currentEdited = getString(record.message, 'edited');
        if (!currentEdited && semantics.messageReferenceId) {
          record.message.edited = semantics.messageReferenceId;
        }
      }
    }

    return record;
  }
}
