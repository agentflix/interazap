import { Injectable, Logger } from '@nestjs/common';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { composeIdempotencyKey } from '../../../shared/utils/idempotency-key.util';
import { IdempotencyDescriptor, StreamPayload } from './chat-webhook.types';
import { PayloadSemanticsResolver } from './payload-semantics-resolver.service';

/**
 * Garante processamento idempotente de eventos de webhook usando deduplicacao via Redis.
 *
 * Contexto: trata eventos de conexao, edicoes de mensagem e atualizacoes de status
 * compondo chaves de idempotencia adequadas ao tipo de evento recebido.
 */
@Injectable()
export class WebhookIdempotencyService {
  private readonly logger = new Logger(WebhookIdempotencyService.name);

  constructor(
    private readonly redisService: RedisService,
    private readonly payloadSemanticsResolver: PayloadSemanticsResolver,
  ) {}

  /**
   * Constroi o descritor de idempotencia para o payload de stream informado.
   *
   * @param payload - Payload de stream a processar
   * @returns Descritor com chave, provider, tipo de evento e semantica
   */
  buildIdempotencyKey(payload: StreamPayload): IdempotencyDescriptor {
    const fallbackId = this.isFallbackPayload(payload)
      ? payload.payload.message?.id
      : undefined;
    const fallbackEvent = this.isFallbackPayload(payload)
      ? payload.payload.event_type
      : undefined;

    const messageId = payload.message?.id ?? fallbackId ?? '';
    const eventType = (
      payload.event_type ??
      fallbackEvent ??
      'unknown'
    ).toString();
    const provider = payload.provider ?? 'unknown';
    const normalizedEventType = eventType.toLowerCase();
    const semantics =
      this.payloadSemanticsResolver.resolvePayloadSemantics(payload);

    let discriminator = messageId;

    if (normalizedEventType === 'connection') {
      discriminator = this.resolveConnectionStatus(payload) ?? 'unknown';
    } else if (semantics.changeKind === 'edited') {
      discriminator = this.resolveEditIdempotencyDiscriminator(
        payload,
        semantics,
      );
    } else if (normalizedEventType === 'messages_update') {
      discriminator =
        this.payloadSemanticsResolver.resolveMessageUpdateDiscriminator(
          payload,
        );
    }

    if (!discriminator) {
      discriminator = 'generic';
    }

    const token = payload.instance_webhook_token ?? '';
    const key = this.composeIdempotencyKey(
      provider,
      eventType,
      token,
      discriminator,
    );

    return {
      key,
      provider,
      eventType,
      normalizedEventType,
      token,
      discriminator,
      semantics,
    };
  }

  /**
   * Verifica idempotencia com timeout para evitar bloqueio do loop de eventos.
   *
   * @param key - Chave de idempotencia a verificar no Redis
   * @param ttlSeconds - TTL em segundos para a chave
   * @param timeoutMs - Timeout da operacao Redis em milissegundos
   * @returns true quando primeiro registro, false quando duplicado, null em timeout ou erro
   */
  async ensureIdempotentWithTimeout(
    key: string,
    ttlSeconds: number,
    timeoutMs: number,
  ): Promise<boolean | null> {
    let timeoutHandle: NodeJS.Timeout | undefined;
    try {
      const timeoutPromise = new Promise<null>((resolve) => {
        timeoutHandle = setTimeout(() => resolve(null), timeoutMs);
      });
      const result = await Promise.race<boolean | null>([
        this.redisService.ensureIdempotent(key, ttlSeconds),
        timeoutPromise,
      ]);
      if (result === null) {
        this.logger.warn(
          `Service idempotency timeout after ${timeoutMs}ms; proceeding`,
        );
      }
      return result;
    } catch {
      return null;
    } finally {
      if (timeoutHandle) clearTimeout(timeoutHandle);
    }
  }

  /**
   * Resolve o discriminador de idempotencia para eventos de edicao de mensagem.
   *
   * @param payload - Payload de stream do evento de edicao
   * @param semantics - Metadados semanticos pre-calculados
   * @returns String discriminadora para composicao da chave
   */
  private resolveEditIdempotencyDiscriminator(
    payload: StreamPayload,
    semantics: IdempotencyDescriptor['semantics'],
  ): string {
    if (semantics.editEventId) {
      return `edit_event:${semantics.editEventId}`;
    }

    const message =
      this.payloadSemanticsResolver.extractMessagePayload(payload);
    const rawMessage =
      this.payloadSemanticsResolver.extractRawMessagePayload(payload);
    const content = this.payloadSemanticsResolver.extractMessageContent(
      message,
      rawMessage,
    );
    const rawPayload = this.payloadSemanticsResolver.extractRawPayload(payload);
    const editTimestamp = this.firstNonEmptyString([
      message ? this.getString(message, 'timestamp') : undefined,
      rawMessage ? this.getString(rawMessage, 'timestamp') : undefined,
      rawMessage ? this.getString(rawMessage, 'messageTimestamp') : undefined,
      rawMessage ? this.getString(rawMessage, 'editTimestamp') : undefined,
      rawMessage ? this.getString(rawMessage, 'editedAt') : undefined,
      rawMessage ? this.getString(rawMessage, 'updatedAt') : undefined,
      rawPayload ? this.getString(rawPayload, 'timestamp') : undefined,
    ]);

    const signature = composeIdempotencyKey([
      semantics.messageReferenceId ?? 'no_ref',
      content,
      editTimestamp,
      payload.provider ?? 'unknown',
      payload.instance_webhook_token ?? '',
    ]);

    return `edit_sig:${signature}`;
  }

  /**
   * Extrai o status de conexao do payload de stream para discriminacao de idempotencia.
   *
   * @param payload - Payload de stream de evento de conexao
   * @returns Status de conexao ou undefined quando nao determinavel
   */
  private resolveConnectionStatus(payload: StreamPayload): string | undefined {
    const raw =
      payload.raw &&
      typeof payload.raw === 'object' &&
      !Array.isArray(payload.raw)
        ? payload.raw
        : {};
    const instance =
      raw.instance &&
      typeof raw.instance === 'object' &&
      !Array.isArray(raw.instance)
        ? (raw.instance as Record<string, unknown>)
        : {};

    const status = this.getString(instance, 'status');
    if (status) {
      return status;
    }

    const statusPayload =
      raw.status && typeof raw.status === 'object' && !Array.isArray(raw.status)
        ? (raw.status as Record<string, unknown>)
        : {};
    const statusValue = this.getString(statusPayload, 'status');
    if (statusValue) {
      return statusValue;
    }

    const connected = statusPayload.connected;
    if (typeof connected === 'boolean') {
      return connected ? 'connected' : 'disconnected';
    }

    return undefined;
  }

  /**
   * Compoe a chave de idempotencia a partir das partes do evento.
   *
   * @param provider - Nome do provedor
   * @param eventType - Tipo do evento
   * @param token - Token do webhook da instancia
   * @param discriminator - Discriminador especifico do evento
   * @returns Chave de idempotencia normalizada
   */
  private composeIdempotencyKey(
    provider: string,
    eventType: string,
    token?: string,
    discriminator?: string,
  ): string {
    const rawKey = `idempo:${[provider, eventType, token, discriminator]
      .filter((value) => Boolean(value))
      .join(':')}`;

    return this.normalizeIdempotencyKey(rawKey);
  }

  /**
   * Normaliza a chave de idempotencia truncando via hash quando excede 255 caracteres.
   *
   * @param key - Chave bruta a normalizar
   * @returns Chave com no maximo 255 caracteres
   */
  private normalizeIdempotencyKey(key: string): string {
    const maxLength = 255;
    if (key.length <= maxLength) {
      return key;
    }

    return `idempo_hash:${composeIdempotencyKey([key])}`;
  }

  /**
   * Retorna o primeiro valor nao vazio do array de candidatos.
   *
   * @param values - Candidatos a string nao vazia
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
   * Extrai um campo string de um Record de forma tipada.
   *
   * @param source - Record de origem
   * @param key - Nome da chave a extrair
   * @returns Valor string ou undefined quando ausente ou de tipo diferente
   */
  private getString(
    source: Record<string, unknown>,
    key: string,
  ): string | undefined {
    const value = source[key];
    return typeof value === 'string' ? value : undefined;
  }

  /**
   * Verifica se o payload e do tipo FallbackStreamPayload.
   *
   * @param payload - Payload a verificar
   * @returns true quando o payload e do tipo fallback
   */
  private isFallbackPayload(
    payload: StreamPayload,
  ): payload is StreamPayload & {
    payload: { event_type?: string; message?: { id?: string } };
  } {
    return (payload as { payload?: unknown }).payload !== undefined;
  }
}
