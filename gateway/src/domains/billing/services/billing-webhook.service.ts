import { BadRequestException, Injectable, Logger } from '@nestjs/common';
import { randomUUID } from 'crypto';
import { Job, Queue } from 'bullmq';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { InternalApiClientService } from '../../../infrastructure/internal-api/internal-api-client.service';
import { BillingTenantResolverService } from './billing-tenant-resolver.service';
import { AsaasNormalizer } from '../providers/asaas/asaas.normalizer';
import { composeIdempotencyKey } from '../../../shared/utils/idempotency-key.util';
import { stableHash } from '../../../shared/utils/hash.util';
import { maskSecrets } from '../../../shared/utils/secret-masker';
import { getRecord, getString } from '../../../shared/utils/type-guards';
import { BullMQQueueFactory } from '../../../shared/services/queue/bullmq-queue-factory.service';

type BillingWebhookPersistenceJob = {
  tenant_id: string;
  token: string;
  provider: string;
  eventType: string;
  providerEventId: string | null;
  payload: Record<string, unknown>;
  payloadHash: string;
  idempotencyKey: string;
  streamId: string | null;
  eventId?: string | null;
};

/**
 * BillingWebhookService
 *
 * Processa eventos de webhook de pagamento recebidos de provedores externos (ex.: Asaas).
 * Normaliza o payload, garante idempotência via banco de dados e publica o evento
 * no Redis Stream para consumo pelo backend Laravel.
 */
@Injectable()
export class BillingWebhookService {
  private static readonly BILLING_WEBHOOK_PERSIST_QUEUE =
    'billing-webhook-persistence';
  private static readonly BILLING_WEBHOOK_IN_FLIGHT_TTL_SECONDS = 86400;

  private readonly logger = new Logger(BillingWebhookService.name);
  private persistenceQueue: Queue<BillingWebhookPersistenceJob> | null = null;
  private persistenceWorkerInitialized = false;

  constructor(
    private readonly redisService: RedisService,
    private readonly internalApiClient: InternalApiClientService,
    private readonly tenantResolver: BillingTenantResolverService,
    private readonly asaasNormalizer: AsaasNormalizer,
    private readonly queueFactory: BullMQQueueFactory,
  ) {}

  /**
   * Processa um evento de webhook de billing recebido de um provedor externo.
   * Garante idempotência, normaliza o payload e publica no Redis Stream.
   *
   * @param provider - Nome do provedor de pagamento (ex.: 'asaas')
   * @param token - Token de autenticação do webhook do tenant
   * @param payload - Payload bruto recebido no webhook
   * @returns Promise que resolve quando o evento for processado ou descartado por duplicidade
   * @throws BadRequestException se o provedor não for suportado
   * @throws UnauthorizedException se o token for inválido
   */
  async handle(
    provider: string,
    token: string,
    payload: Record<string, unknown>,
  ): Promise<void> {
    const startedAt = Date.now();
    const correlationId = randomUUID();

    const normalizedProvider = provider.toLowerCase();
    if (normalizedProvider !== 'asaas') {
      throw new BadRequestException('Unsupported billing provider');
    }

    this.logger.debug('Processing billing event', maskSecrets({ token }));

    const tenant = await this.tenantResolver.resolveByWebhookToken(token);

    const normalizedEvent = this.asaasNormalizer.normalize(
      tenant.tenant_id,
      payload,
    );

    const eventType = normalizedEvent.eventType;
    const providerEventId =
      normalizedEvent.payment.id !== 'unknown'
        ? normalizedEvent.payment.id
        : this.resolveProviderEventId(payload);
    const payloadHash = stableHash(payload);
    const idempotencyKey =
      normalizedEvent.payment.id !== 'unknown'
        ? normalizedEvent.idempotencyKey
        : composeIdempotencyKey([
            normalizedProvider,
            eventType,
            providerEventId ?? payloadHash,
          ]);

    const inFlightKey = `billing:webhook:in_flight:${tenant.tenant_id}:${idempotencyKey}`;
    const durableDedupKey = `billing:webhook:processed:${tenant.tenant_id}:${idempotencyKey}`;

    const durableMarker = await this.redisService.get(durableDedupKey);
    if (durableMarker) {
      const durationMs = Date.now() - startedAt;
      this.logger.debug('Duplicate billing event skipped', {
        provider: normalizedProvider,
        tenant_id: tenant.tenant_id,
        event_type: eventType,
        idempotency_key: idempotencyKey,
        duplicate_reason: 'durable_marker',
        correlation_id: correlationId,
        duration_ms: durationMs,
      });
      return;
    }

    const created = await this.redisService.ensureIdempotent(
      inFlightKey,
      BillingWebhookService.BILLING_WEBHOOK_IN_FLIGHT_TTL_SECONDS,
    );

    if (!created) {
      const durationMs = Date.now() - startedAt;
      this.logger.debug('Duplicate billing event skipped', {
        provider: normalizedProvider,
        tenant_id: tenant.tenant_id,
        event_type: eventType,
        idempotency_key: idempotencyKey,
        duplicate_reason: 'in_flight',
        correlation_id: correlationId,
        duration_ms: durationMs,
      });
      return;
    }

    let streamId: string | null = null;
    try {
      streamId = await this.redisService.publishStream(
        'billing.payment_received',
        {
          tenant_id: tenant.tenant_id,
          provider: normalizedProvider.toUpperCase(),
          event_type: eventType,
          provider_event_id: providerEventId ?? '',
          idempotency_key: idempotencyKey,
          payload_hash: payloadHash,
          received_at: new Date().toISOString(),
          correlation_id: correlationId,
          payload,
        },
      );

      await this.redisService.set(
        durableDedupKey,
        JSON.stringify({
          stream_id: streamId,
          payload_hash: payloadHash,
          received_at: new Date().toISOString(),
        }),
      );

      this.enqueuePersistence(
        {
          tenant_id: tenant.tenant_id,
          token,
          provider: normalizedProvider,
          eventType,
          providerEventId,
          payload,
          payloadHash,
          idempotencyKey,
          streamId: streamId ?? null,
        },
        correlationId,
      );
    } finally {
      await this.redisService.delete(inFlightKey);
    }

    const durationMs = Date.now() - startedAt;
    this.logger.log('Billing webhook processed', {
      provider: normalizedProvider,
      tenant_id: tenant.tenant_id,
      event_type: eventType,
      provider_event_id: providerEventId,
      idempotency_key: idempotencyKey,
      stream_id: streamId,
      correlation_id: correlationId,
      duration_ms: durationMs,
    });
  }

  /**
   * Enfileira persistência de rastreabilidade fora do hot path de ACK.
   */
  private enqueuePersistence(
    jobData: BillingWebhookPersistenceJob,
    correlationId: string,
  ): void {
    const queue = this.ensurePersistenceQueue();
    void queue
      .add('persist-billing-webhook-event', jobData)
      .catch((error: unknown) => {
        this.logger.error('Failed to enqueue billing persistence job', {
          tenant_id: jobData.tenant_id,
          idempotency_key: jobData.idempotencyKey,
          correlation_id: correlationId,
          message: error instanceof Error ? error.message : String(error),
        });

        // Fallback assíncrono para manter rastreabilidade quando a fila falha.
        void this.persistWithoutQueue(jobData, correlationId);
      });
  }

  /**
   * Persistência de fallback quando o enqueue da fila falha.
   */
  private async persistWithoutQueue(
    jobData: BillingWebhookPersistenceJob,
    correlationId: string,
  ): Promise<void> {
    try {
      const eventId = await this.persistRawEvent({
        tenant_id: jobData.tenant_id,
        token: jobData.token,
        provider: jobData.provider,
        eventType: jobData.eventType,
        providerEventId: jobData.providerEventId,
        payload: jobData.payload,
        payloadHash: jobData.payloadHash,
        idempotencyKey: jobData.idempotencyKey,
      });

      const resolvedEventId = eventId ?? jobData.eventId ?? null;
      if (jobData.streamId && resolvedEventId) {
        await this.setStreamId(resolvedEventId, jobData.streamId);
      }

      this.logger.warn('Billing persistence fallback completed', {
        tenant_id: jobData.tenant_id,
        idempotency_key: jobData.idempotencyKey,
        correlation_id: correlationId,
      });
    } catch (error: unknown) {
      this.logger.error('Billing persistence fallback failed', {
        tenant_id: jobData.tenant_id,
        idempotency_key: jobData.idempotencyKey,
        correlation_id: correlationId,
        message: error instanceof Error ? error.message : String(error),
      });
    }
  }

  /**
   * Inicializa fila e worker dedicados para persistência assíncrona do webhook.
   */
  private ensurePersistenceQueue(): Queue<BillingWebhookPersistenceJob> {
    if (!this.persistenceQueue) {
      this.persistenceQueue =
        this.queueFactory.createQueue<BillingWebhookPersistenceJob>(
          BillingWebhookService.BILLING_WEBHOOK_PERSIST_QUEUE,
        );
    }

    if (!this.persistenceWorkerInitialized) {
      this.queueFactory.createWorker<BillingWebhookPersistenceJob, void>(
        BillingWebhookService.BILLING_WEBHOOK_PERSIST_QUEUE,
        (job) => this.processPersistenceJob(job),
      );
      this.persistenceWorkerInitialized = true;
    }

    return this.persistenceQueue;
  }

  /**
   * Worker de persistência para escrever rastreabilidade via api/ HTTP.
   */
  private async processPersistenceJob(
    job: Job<BillingWebhookPersistenceJob>,
  ): Promise<void> {
    const { data } = job;

    const eventId = await this.persistRawEvent({
      tenant_id: data.tenant_id,
      token: data.token,
      provider: data.provider,
      eventType: data.eventType,
      providerEventId: data.providerEventId,
      payload: data.payload,
      payloadHash: data.payloadHash,
      idempotencyKey: data.idempotencyKey,
    });

    const resolvedEventId = eventId ?? data.eventId ?? null;
    if (data.streamId && resolvedEventId) {
      await this.setStreamId(resolvedEventId, data.streamId);
    }
  }

  /**
   * Tenta extrair o identificador do evento diretamente do payload bruto.
   * Usado como fallback quando o normalizador não consegue identificar o evento.
   *
   * @param payload - Payload bruto do webhook
   * @returns ID do evento extraído do payload ou null se não encontrado
   */
  private resolveProviderEventId(
    payload: Record<string, unknown>,
  ): string | null {
    const payment = getRecord(payload, 'payment');
    const id = payment ? getString(payment, 'id') : undefined;
    if (id) {
      return id;
    }

    return getString(payload, 'id') ?? null;
  }

  /**
   * Persiste o evento bruto de webhook via api/ HTTP.
   * Retorna o event_id criado ou null em caso de conflito idempotente.
   */
  private async persistRawEvent(params: {
    tenant_id: string;
    token: string;
    provider: string;
    eventType: string;
    providerEventId: string | null;
    payload: Record<string, unknown>;
    payloadHash: string;
    idempotencyKey: string;
  }): Promise<string | null> {
    try {
      const response = await this.internalApiClient.post<{
        success: boolean;
        data: { event_id: string; created_at: string };
      }>(
        '/api/internal/billing/webhook-events',
        {
          tenant_id: params.tenant_id,
          event_type: params.eventType,
          payload: {
            ...params.payload,
            provider: params.provider.toUpperCase(),
            token: params.token,
            provider_event_id: params.providerEventId,
            idempotency_key: params.idempotencyKey,
            payload_hash: params.payloadHash,
          },
          external_id: params.providerEventId ?? params.idempotencyKey,
        },
        'billing_event_create',
      );

      return response.data?.event_id ?? null;
    } catch (error) {
      this.logger.warn(
        `persistRawEvent failed: ${error instanceof Error ? error.message : String(error)}`,
      );
      return null;
    }
  }

  /**
   * Atualiza o stream_id do evento via api/ HTTP.
   */
  private async setStreamId(eventId: string, streamId: string): Promise<void> {
    try {
      await this.internalApiClient.patch(
        `/api/internal/billing/webhook-events/${encodeURIComponent(eventId)}/stream-id`,
        { stream_id: streamId },
        'billing_event_stream_id',
      );
    } catch (error) {
      this.logger.warn(
        `setStreamId failed for event ${eventId}: ${error instanceof Error ? error.message : String(error)}`,
      );
    }
  }
}
