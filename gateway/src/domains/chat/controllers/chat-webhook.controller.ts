import {
  Body,
  Controller,
  Logger,
  Param,
  Post,
  Req,
  UseGuards,
  UseInterceptors,
  UsePipes,
  ValidationPipe,
} from '@nestjs/common';
import { ThrottlerGuard } from '@nestjs/throttler';
import { ChatWebhookService } from '../services/chat-webhook.service';
import { WebhookEventDto } from '../dto/webhook-event.dto';
import type { Request } from 'express';
import { WebhookNormalizationInterceptor } from '../interceptors/webhook-normalization.interceptor';
import { InstanceResolverService } from '../services/instance-resolver.service';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { MetricsService } from '../../../metrics/metrics.service';
import { composeIdempotencyKey } from '../../../shared/utils/idempotency-key.util';
import { AckOutcome } from '../models/webhook.model';

export type { AckOutcome };

/**
 * Controller de recebimento de webhooks do dominio Chat com ACK imediato.
 * Rate-limiting aplicado para proteger contra floods de requests externos.
 */
@UseGuards(ThrottlerGuard)
@Controller({
  version: '1',
  path: 'webhooks/:provider/instances/:instance_webhook_token',
})
export class ChatWebhookController {
  private readonly logger = new Logger(ChatWebhookController.name);

  /**
   * Initializes the webhook controller with required services.
   *
   * @param chatWebhookService - Service for processing incoming webhooks
   * @param instanceResolverService - Service for resolving tenant/instance from webhook token
   * @param redisService - Redis service for idempotency checks
   * @param metricsService - Metrics service for recording ACK latency and outcomes
   */
  constructor(
    private readonly chatWebhookService: ChatWebhookService,
    private readonly instanceResolverService: InstanceResolverService,
    private readonly redisService: RedisService,
    private readonly metricsService: MetricsService,
  ) {}

  /**
   * Recebe webhook, aplica pre-check de idempotencia e responde ACK sem bloqueio.
   */
  @Post()
  @UsePipes(
    new ValidationPipe({
      whitelist: true,
      transform: true,
    }),
  )
  @UseInterceptors(WebhookNormalizationInterceptor)
  async handleWebhook(
    @Param('provider') provider: string,
    @Param('instance_webhook_token') token: string,
    @Body() payload: WebhookEventDto,
    @Req() request: Request,
  ): Promise<{ success: boolean }> {
    const ackStartedAt = Date.now();
    const providerLabel = this.normalizeMetricLabel(provider);
    let tenantLabel = 'unknown';
    let ackOutcome: AckOutcome = 'accepted_unkeyed';

    this.logger.log(`Webhook received from ${provider}`);
    // Preserve the original raw body to avoid whitelist stripping fields
    const body: unknown = request.body;
    const rawBody =
      typeof body === 'object' && body !== null
        ? (body as Record<string, unknown>)
        : undefined;
    const enrichedPayload: WebhookEventDto = {
      ...payload,
      raw: rawBody,
    };

    // Resolution is deferred to fire-and-forget handler to keep ACK < 150ms.
    // service.handle() already falls back: resolvedOverride ?? resolveByWebhookToken(token)
    tenantLabel = 'deferred';

    try {
      const preAck = this.buildPreAckIdempotency(
        provider,
        token,
        enrichedPayload,
      );
      if (preAck?.bypass === true) {
        ackOutcome = 'connection_bypass';
      } else if (preAck?.key) {
        const isFirstSeen = await this.ensurePreAckIdempotentWithTimeout(
          preAck.key,
          120,
        );
        if (isFirstSeen === false) {
          ackOutcome = 'duplicate';
          this.recordAckMetric(
            ackStartedAt,
            providerLabel,
            tenantLabel,
            ackOutcome,
          );
          return { success: true };
        }
        if (isFirstSeen === true) {
          ackOutcome = 'first_seen';
        } else {
          ackOutcome = 'idempotency_error';
        }
      }
    } catch (error: unknown) {
      ackOutcome = 'idempotency_error';
      this.logger.error(
        `ACK pre-check idempotency failed: ${error instanceof Error ? error.message : String(error)}`,
        error instanceof Error ? error.stack : undefined,
      );
    }

    // Fire-and-forget: ACK the webhook immediately (< 150ms target).
    // Processing (DB writes, stream publish) runs in the background.
    // resolvedInstance is null — handle() will resolve lazily.
    this.chatWebhookService
      .handle(provider, token, enrichedPayload, null)
      .catch((error: unknown) => {
        this.logger.error(
          `Webhook processing failed: ${error instanceof Error ? error.message : String(error)}`,
          error instanceof Error ? error.stack : undefined,
        );
      });

    this.recordAckMetric(ackStartedAt, providerLabel, tenantLabel, ackOutcome);

    return Promise.resolve({ success: true });
  }

  /**
   * Constroi o descritor de idempotencia para o pre-ACK sem bloquear o fluxo.
   *
   * @param provider - Nome do provedor
   * @param token - Token do webhook da instancia
   * @param payload - Payload do evento recebido
   * @returns Descritor com chave e flag de bypass ou null
   */
  private buildPreAckIdempotency(
    provider: string,
    token: string,
    payload: WebhookEventDto,
  ): { key?: string; bypass: boolean } | null {
    const eventType = this.normalizeEventType(payload);
    if (eventType.length === 0) {
      return null;
    }

    if (eventType.startsWith('connection')) {
      return { bypass: true };
    }

    const discriminator = this.resolvePreAckDiscriminator(eventType, payload);
    if (discriminator === null) {
      return { bypass: false };
    }

    return {
      bypass: false,
      key: `idempo:ack:${provider.toLowerCase()}:${token}:${eventType}:${discriminator}`,
    };
  }

  /**
   * Verifica idempotencia do pre-ACK com timeout para manter latencia abaixo de 150ms.
   *
   * @param key - Chave de idempotencia a verificar
   * @param ttlSeconds - TTL em segundos da chave
   * @param timeoutMs - Timeout da operacao Redis em milissegundos
   * @returns true quando primeiro registro, false quando duplicado, null em timeout
   */
  private async ensurePreAckIdempotentWithTimeout(
    key: string,
    ttlSeconds: number,
    timeoutMs = 150,
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
          `ACK pre-check idempotency timeout after ${timeoutMs}ms; proceeding without pre-ack dedupe`,
        );
      }

      return result;
    } catch {
      return null;
    } finally {
      if (timeoutHandle) {
        clearTimeout(timeoutHandle);
      }
    }
  }

  /**
   * Extrai e normaliza o tipo de evento do payload do webhook.
   *
   * @param payload - Payload do evento recebido
   * @returns Tipo de evento em minusculas ou string vazia
   */
  private normalizeEventType(payload: WebhookEventDto): string {
    const candidates = [payload.event_type, payload.EventType];
    for (const candidate of candidates) {
      if (typeof candidate === 'string' && candidate.trim().length > 0) {
        return candidate.trim().toLowerCase();
      }
    }
    return '';
  }

  /**
   * Resolve o discriminador de idempotencia do pre-ACK baseado no tipo de evento.
   *
   * @param eventType - Tipo de evento normalizado
   * @param payload - Payload do evento recebido
   * @returns String discriminadora ou null quando nao aplicavel
   */
  private resolvePreAckDiscriminator(
    eventType: string,
    payload: WebhookEventDto,
  ): string | null {
    if (eventType === 'messages') {
      const editedReferenceId = this.resolvePreAckEditedReferenceId(payload);
      if (editedReferenceId) {
        const editedEventId = this.resolvePreAckEditedEventId(payload);
        if (editedEventId) {
          return `edit_event:${editedEventId}`;
        }

        const editedSignature = composeIdempotencyKey([
          editedReferenceId,
          this.resolvePreAckEditedContent(payload),
          this.resolvePreAckEditedTimestamp(payload),
        ]);

        return `edit_sig:${editedSignature}`;
      }
    }

    if (eventType === 'messages_update') {
      const eventPayload = this.extractNestedEvent(payload.raw);
      const messageIds = Array.isArray(eventPayload?.MessageIDs)
        ? eventPayload.MessageIDs.filter(
            (value): value is string => typeof value === 'string',
          )
        : [];
      const statusType =
        typeof eventPayload?.Type === 'string' ? eventPayload.Type : 'unknown';

      if (messageIds.length > 0) {
        return `${messageIds.join(',')}_${statusType}`;
      }

      if (
        typeof eventPayload?.Timestamp === 'string' ||
        typeof eventPayload?.Timestamp === 'number'
      ) {
        return `ts_${String(eventPayload.Timestamp)}_${statusType}`;
      }

      return null;
    }

    const rawRecord =
      payload.raw && typeof payload.raw === 'object' ? payload.raw : undefined;
    const messageFromRaw =
      rawRecord &&
      typeof rawRecord.message === 'object' &&
      rawRecord.message !== null
        ? (rawRecord.message as Record<string, unknown>)
        : undefined;

    const candidateIds = [
      payload.message?.id,
      typeof rawRecord?.messageId === 'string'
        ? rawRecord.messageId
        : undefined,
      typeof messageFromRaw?.id === 'string' ? messageFromRaw.id : '',
    ];

    for (const candidate of candidateIds) {
      if (typeof candidate === 'string' && candidate.trim().length > 0) {
        return candidate.trim();
      }
    }

    return null;
  }

  /**
   * Extrai o ID de referencia da mensagem editada para deduplicacao.
   *
   * @param payload - Payload do evento recebido
   * @returns ID da mensagem original editada ou null
   */
  private resolvePreAckEditedReferenceId(
    payload: WebhookEventDto,
  ): string | null {
    const rawMessage = this.extractRawMessage(payload);
    const payloadMessage =
      payload.message && typeof payload.message === 'object'
        ? (payload.message as Record<string, unknown>)
        : undefined;

    const candidates = [
      typeof payloadMessage?.edited === 'string' ? payloadMessage.edited : null,
      typeof rawMessage?.edited === 'string' ? rawMessage.edited : null,
    ];

    for (const candidate of candidates) {
      if (candidate && candidate.trim().length > 0) {
        return candidate.trim();
      }
    }

    return null;
  }

  /**
   * Extrai o ID do evento de edicao para deduplicacao.
   *
   * @param payload - Payload do evento recebido
   * @returns ID do evento de edicao ou null
   */
  private resolvePreAckEditedEventId(payload: WebhookEventDto): string | null {
    const rawRecord =
      payload.raw && typeof payload.raw === 'object' ? payload.raw : undefined;
    const rawMessage = this.extractRawMessage(payload);
    const payloadMessage =
      payload.message && typeof payload.message === 'object'
        ? (payload.message as Record<string, unknown>)
        : undefined;

    const candidates = [
      payload.message?.id,
      typeof payloadMessage?.id === 'string' ? payloadMessage.id : null,
      typeof rawMessage?.id === 'string' ? rawMessage.id : null,
      typeof rawMessage?.messageid === 'string' ? rawMessage.messageid : null,
      typeof rawMessage?.messageId === 'string' ? rawMessage.messageId : null,
      typeof rawRecord?.messageId === 'string' ? rawRecord.messageId : null,
      typeof rawRecord?.id === 'string' ? rawRecord.id : null,
    ];

    for (const candidate of candidates) {
      if (typeof candidate === 'string' && candidate.trim().length > 0) {
        return candidate.trim();
      }
    }

    return null;
  }

  /**
   * Extrai o conteudo da mensagem editada para compor a assinatura de deduplicacao.
   *
   * @param payload - Payload do evento recebido
   * @returns Conteudo da mensagem editada ou null
   */
  private resolvePreAckEditedContent(payload: WebhookEventDto): string | null {
    const rawMessage = this.extractRawMessage(payload);
    const payloadMessage =
      payload.message && typeof payload.message === 'object'
        ? (payload.message as Record<string, unknown>)
        : undefined;

    const candidates = [
      typeof payloadMessage?.body === 'string' ? payloadMessage.body : null,
      typeof payloadMessage?.text === 'string' ? payloadMessage.text : null,
      typeof rawMessage?.body === 'string' ? rawMessage.body : null,
      typeof rawMessage?.text === 'string' ? rawMessage.text : null,
      typeof rawMessage?.caption === 'string' ? rawMessage.caption : null,
    ];

    for (const candidate of candidates) {
      if (candidate && candidate.trim().length > 0) {
        return candidate.trim();
      }
    }

    return null;
  }

  /**
   * Extrai o timestamp da mensagem editada para compor a assinatura de deduplicacao.
   *
   * @param payload - Payload do evento recebido
   * @returns Timestamp como string ou numero, ou null
   */
  private resolvePreAckEditedTimestamp(
    payload: WebhookEventDto,
  ): string | number | null {
    const rawRecord =
      payload.raw && typeof payload.raw === 'object' ? payload.raw : undefined;
    const rawMessage = this.extractRawMessage(payload);
    const payloadMessage =
      payload.message && typeof payload.message === 'object'
        ? (payload.message as Record<string, unknown>)
        : undefined;

    const candidates = [
      payload.message?.timestamp,
      payloadMessage?.timestamp,
      rawMessage?.timestamp,
      rawMessage?.messageTimestamp,
      rawMessage?.editTimestamp,
      rawRecord?.timestamp,
    ];

    for (const candidate of candidates) {
      if (typeof candidate === 'string' && candidate.trim().length > 0) {
        return candidate.trim();
      }

      if (typeof candidate === 'number' && Number.isFinite(candidate)) {
        return candidate;
      }
    }

    return null;
  }

  /**
   * Extrai a mensagem bruta aninhada no campo raw do payload.
   *
   * @param payload - Payload do evento recebido
   * @returns Record da mensagem bruta ou null
   */
  private extractRawMessage(
    payload: WebhookEventDto,
  ): Record<string, unknown> | null {
    const rawRecord =
      payload.raw && typeof payload.raw === 'object' ? payload.raw : undefined;

    if (
      rawRecord?.message &&
      typeof rawRecord.message === 'object' &&
      rawRecord.message !== null
    ) {
      return rawRecord.message as Record<string, unknown>;
    }

    return null;
  }

  /**
   * Extrai o objeto de evento aninhado no campo raw.event ou raw.raw.event.
   *
   * @param raw - Record do raw payload
   * @returns Record do evento aninhado ou undefined
   */
  private extractNestedEvent(
    raw: Record<string, unknown> | undefined,
  ): Record<string, unknown> | undefined {
    if (!raw) {
      return undefined;
    }

    if (
      raw.event &&
      typeof raw.event === 'object' &&
      !Array.isArray(raw.event)
    ) {
      return raw.event as Record<string, unknown>;
    }

    if (raw.raw && typeof raw.raw === 'object' && !Array.isArray(raw.raw)) {
      return this.extractNestedEvent(raw.raw as Record<string, unknown>);
    }

    return undefined;
  }

  /**
   * Normaliza um label de metrica para letras minusculas sem espacos.
   *
   * @param value - Valor bruto do label
   * @returns Label normalizado ou 'unknown'
   */
  private normalizeMetricLabel(value: string): string {
    const normalized = value.trim().toLowerCase();
    return normalized.length > 0 ? normalized : 'unknown';
  }

  /**
   * Registra a metrica de ACK com latencia e resultado do fluxo.
   *
   * @param ackStartedAt - Timestamp de inicio do ACK em ms
   * @param providerLabel - Label do provedor para a metrica
   * @param tenantLabel - Label do tenant para a metrica
   * @param outcome - Resultado do pre-ACK de idempotencia
   */
  private recordAckMetric(
    ackStartedAt: number,
    providerLabel: string,
    tenantLabel: string,
    outcome: AckOutcome,
  ): void {
    const ackLatencyMs = Date.now() - ackStartedAt;
    this.metricsService.recordWebhookAck(
      providerLabel,
      tenantLabel,
      outcome,
      ackLatencyMs,
    );
  }
}
