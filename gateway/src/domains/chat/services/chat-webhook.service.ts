import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { WebhookEventDto } from '../dto/webhook-event.dto';
import { UazapiProvider } from '../providers/uazapi/uazapi.provider';
import { ZapiAdapter } from '../providers/zapi/zapi.adapter';
import { MetaAdapter } from '../providers/meta/meta.adapter';
import { isUazapiWebhookEvent } from '../providers/uazapi/uazapi.dto';
import { InstanceResolverService } from './instance-resolver.service';
import { composeIdempotencyKey } from '../../../shared/utils/idempotency-key.util';

import { GatewayFileLogger } from '../../../common/logger/gateway-file-logger';
import { GatewayConfigService } from '../../../shared/services/gateway-config.service';
import { ChatWebhookFileLoggerService } from './chat-webhook-file-logger.service';
import { ResolvedInstance } from '../models/instance.model';
import {
  FallbackStreamPayload,
  IdempotencyDescriptor,
  StreamPayload,
  UazapiStreamPayload,
} from './chat-webhook.types';
import { WebhookIdempotencyService } from './webhook-idempotency.service';
import { ChatWebhookEventNormalizer } from './chat-webhook-event-normalizer.service';
import { ChatWebhookRealtimeProcessor } from './chat-webhook-realtime-processor.service';

/**
 * Orquestra normalizacao, idempotencia e publicacao de eventos de webhook do chat.
 */
@Injectable()
export class ChatWebhookService {
  private readonly logger = new Logger(ChatWebhookService.name);
  private readonly fileLogger = new GatewayFileLogger(ChatWebhookService.name);

  constructor(
    private readonly configService: ConfigService,
    private readonly gatewayConfigService: GatewayConfigService,
    private readonly redisService: RedisService,
    private readonly uazapiProvider: UazapiProvider,
    private readonly zapiAdapter: ZapiAdapter,
    private readonly metaAdapter: MetaAdapter,
    private readonly instanceResolver: InstanceResolverService,
    private readonly webhookFileLogger: ChatWebhookFileLoggerService,
    private readonly eventNormalizer: ChatWebhookEventNormalizer,
    private readonly realtimeProcessor: ChatWebhookRealtimeProcessor,
    private readonly webhookIdempotency: WebhookIdempotencyService,
  ) {}

  /**
   * Processa um webhook recebido e encaminha eventos normalizados para realtime e stream.
   */
  async handle(
    provider: string,
    token: string,
    event: WebhookEventDto,
    resolvedOverride: ResolvedInstance | null = null,
  ): Promise<void> {
    this.logger.debug('Processing event for webhook');

    const resolveStartedAt = Date.now();

    // Early normalize for uazapi — reused below to avoid double-normalization.
    const earlyNormalized: ReturnType<UazapiProvider['normalize']> | null =
      provider.toLowerCase() === 'uazapi' && isUazapiWebhookEvent(event)
        ? this.uazapiProvider.normalize(token, event)
        : null;

    const resolved =
      resolvedOverride ??
      (await this.instanceResolver.resolveByWebhookToken(token));
    const resolveDurationMs = Date.now() - resolveStartedAt;
    if (resolveDurationMs > 300) {
      this.logger.warn(`Instance resolve latency ${resolveDurationMs}ms`);
    } else {
      this.logger.debug(`Instance resolved in ${resolveDurationMs}ms`);
    }

    if (provider.toLowerCase() === 'uazapi') {
      if (!earlyNormalized) {
        this.logger.warn('Uazapi event failed type guard — skipping');
        return;
      }
      const normalized: UazapiStreamPayload = {
        ...earlyNormalized,
        tenant_id: resolved.tenant_id,
      };
      this.logger.debug(
        `Normalized webhook: ${JSON.stringify({ event_type: normalized.event_type, message_id: normalized.message?.id })}`,
      );
      this.logger.debug('Normalized raw payload captured');
      await this.processStreamPayload(normalized, resolved);
      return;
    }

    if (provider.toLowerCase() === 'zapi') {
      const rawPayload: Record<string, unknown> = event.raw ?? { ...event };
      const normalized = await this.zapiAdapter.normalizeWebhook(
        token,
        rawPayload,
        resolved.tenant_id,
        resolved.instance_id ?? '',
      );

      const streamPayload =
        this.eventNormalizer.mapZapiNormalizedToStream(normalized);
      this.logger.debug(
        `Normalized webhook: ${JSON.stringify({ event_type: streamPayload.event_type, message_id: streamPayload.message?.id })}`,
      );
      this.logger.debug('Normalized raw payload captured');

      await this.processStreamPayload(streamPayload, resolved);
      return;
    }

    if (provider.toLowerCase() === 'meta') {
      const rawPayload: Record<string, unknown> = event.raw ?? { ...event };
      const normalized = await this.metaAdapter.normalizeWebhook(
        token,
        rawPayload,
      );

      const streamPayload =
        this.eventNormalizer.mapZapiNormalizedToStream(normalized);
      this.logger.debug(
        `Normalized webhook: ${JSON.stringify({ event_type: streamPayload.event_type, message_id: streamPayload.message?.id })}`,
      );
      this.logger.debug('Normalized raw payload captured');

      await this.processStreamPayload(streamPayload, resolved);
      return;
    }

    const fallbackPayload: FallbackStreamPayload = {
      provider,
      instance_webhook_token: token,
      payload: event,
      tenant_id: resolved.tenant_id,
      event_type: event.event_type,
      direction: event.direction,
      raw: event.raw,
      message: event.message,
    };
    await this.processStreamPayload(fallbackPayload, resolved);
  }

  /**
   * Normaliza, verifica idempotencia e publica o payload no stream Redis.
   *
   * @param payload - Payload de stream a processar
   * @param resolved - Instancia resolvida do webhook
   */
  private async processStreamPayload(
    payload: StreamPayload,
    resolved: ResolvedInstance,
  ): Promise<void> {
    const resolvedInstanceId =
      resolved.instance_id ?? payload.instance_id ?? null;

    const enrichedPayload: StreamPayload = {
      ...payload,
      instance_id: resolvedInstanceId,
    };

    const idempotencyDescriptor = this.buildIdempotencyKey(enrichedPayload);

    this.fileLogger.info('WEBHOOK RECEIVED', {
      eventType: idempotencyDescriptor.eventType,
      normalizedEventType: idempotencyDescriptor.normalizedEventType,
      discriminator: idempotencyDescriptor.discriminator,
      idempotencyKey: idempotencyDescriptor.key,
      token: idempotencyDescriptor.token ? '***' : null,
    });

    // Connection events bypass idempotency — they must always propagate
    const isConnectionEvent =
      idempotencyDescriptor.normalizedEventType.startsWith('connection');
    const isPresenceEvent =
      idempotencyDescriptor.normalizedEventType === 'presence';

    if (!isConnectionEvent && !isPresenceEvent) {
      const isNew = await this.ensureIdempotentWithTimeout(
        idempotencyDescriptor.key,
        600,
        500,
      );
      if (isNew === false) {
        this.logger.debug(
          `Duplicate event skipped: ${idempotencyDescriptor.key}`,
        );
        this.fileLogger.info('DUPLICATE SKIPPED', {
          idempotencyKey: idempotencyDescriptor.key,
          eventType: idempotencyDescriptor.eventType,
          discriminator: idempotencyDescriptor.discriminator,
        });
        return;
      }
    }

    this.fileLogger.info('EVENT ACCEPTED', {
      idempotencyKey: idempotencyDescriptor.key,
      eventType: idempotencyDescriptor.eventType,
      discriminator: idempotencyDescriptor.discriminator,
      bypassedIdempotency: isConnectionEvent,
    });

    await this.realtimeProcessor.emitRealtime(
      enrichedPayload,
      resolvedInstanceId ?? undefined,
      idempotencyDescriptor.semantics,
    );

    this.webhookFileLogger.logWebhook({
      tenant_id: enrichedPayload.tenant_id,
      provider: enrichedPayload.provider ?? 'unknown',
      event_type:
        enrichedPayload.event_type ??
        (this.eventNormalizer.isFallbackPayload(enrichedPayload)
          ? enrichedPayload.payload.event_type
          : 'unknown'),
      direction:
        enrichedPayload.direction ??
        (this.eventNormalizer.isFallbackPayload(enrichedPayload)
          ? enrichedPayload.payload.direction
          : null),
      idempotency_key: idempotencyDescriptor.key,
      instance_webhook_token: enrichedPayload.instance_webhook_token ?? '',
      payload: enrichedPayload,
    });

    // Update chat_instances.status directly for connection events
    if (
      idempotencyDescriptor.normalizedEventType.startsWith('connection') &&
      resolvedInstanceId
    ) {
      await this.realtimeProcessor.updateInstanceConnectionStatus(
        resolvedInstanceId,
        idempotencyDescriptor.discriminator,
        enrichedPayload,
      );
    }

    const streamRecord = this.eventNormalizer.toStreamRecord(enrichedPayload);
    await this.redisService.publishStream(
      this.gatewayConfigService.chatInboundStream,
      streamRecord,
    );
  }

  /**
   * Delega verificacao de idempotencia com timeout para o servico especializado.
   */
  private async ensureIdempotentWithTimeout(
    key: string,
    ttlSeconds: number,
    timeoutMs: number,
  ): Promise<boolean | null> {
    return this.webhookIdempotency.ensureIdempotentWithTimeout(
      key,
      ttlSeconds,
      timeoutMs,
    );
  }

  /**
   * Constroi o descritor de idempotencia delegando para o servico especializado.
   */
  private buildIdempotencyKey(payload: StreamPayload): IdempotencyDescriptor {
    return this.webhookIdempotency.buildIdempotencyKey(payload);
  }

  // ──────────────────────────────────────────────────────────────
  // Legacy compatibility — used by existing private-method tests
  // ──────────────────────────────────────────────────────────────

  /**
   * Compoe chave de idempotencia a partir de partes concatenadas.
   * Mantido para compatibilidade com testes de metodos privados.
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
   * Normaliza a chave de idempotencia truncando via hash quando excede 255 chars.
   * Mantido para compatibilidade com testes de metodos privados.
   */
  private normalizeIdempotencyKey(key: string): string {
    const MAX_LENGTH = 255;
    if (key.length <= MAX_LENGTH) {
      return key;
    }

    const hashedKey = `idempo_hash:${composeIdempotencyKey([key])}`;

    this.fileLogger.info('Idempotency key truncated via hash', {
      originalLength: key.length,
      hashedKey,
    });

    return hashedKey;
  }

  /**
   * Extrai o status de conexao do payload normalizado.
   * Mantido para compatibilidade com testes de metodos privados.
   */
  private resolveConnectionStatus(payload: StreamPayload): string | undefined {
    return this.eventNormalizer.resolveConnectionStatus(payload);
  }
}
