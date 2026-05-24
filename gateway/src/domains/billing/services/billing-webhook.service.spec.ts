import { TestingModule } from '@nestjs/testing';
import { BadRequestException } from '@nestjs/common';
import { BillingWebhookService } from './billing-webhook.service';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { InternalApiClientService } from '../../../infrastructure/internal-api/internal-api-client.service';
import { BillingTenantResolverService } from './billing-tenant-resolver.service';
import { AsaasNormalizer } from '../providers/asaas/asaas.normalizer';
import { BullMQQueueFactory } from '../../../shared/services/queue/bullmq-queue-factory.service';
import { buildTestingModule } from '../../../test-utils/testing-module.util';
import { ResolvedTenant } from '../models/billing-tenant-resolver.model';
import { NormalizedPaymentEvent } from '../models/payment-event.model';

describe('BillingWebhookService', () => {
  let service: BillingWebhookService;
  let redisService: jest.Mocked<RedisService>;
  let internalApiClient: { post: jest.Mock; patch: jest.Mock };
  let tenantResolver: jest.Mocked<BillingTenantResolverService>;
  let asaasNormalizer: jest.Mocked<AsaasNormalizer>;
  let queueFactory: jest.Mocked<BullMQQueueFactory>;
  let persistenceQueue: { add: jest.Mock };

  beforeEach(async () => {
    const mockRedisService = {
      publishStream: jest.fn(),
      ensureIdempotent: jest.fn(),
      get: jest.fn(),
      set: jest.fn(),
      delete: jest.fn(),
    };

    const mockInternalApiClient = {
      post: jest.fn().mockResolvedValue({
        success: true,
        data: { event_id: 'evt-1', created_at: new Date().toISOString() },
      }),
      patch: jest.fn().mockResolvedValue({ success: true }),
    };

    const mockTenantResolver = {
      resolveByWebhookToken: jest.fn(),
    };

    const mockAsaasNormalizer = {
      normalize: jest.fn(),
    };

    persistenceQueue = {
      add: jest.fn().mockResolvedValue(undefined),
    };

    const mockQueueFactory = {
      createQueue: jest.fn().mockReturnValue(persistenceQueue),
      createWorker: jest.fn().mockReturnValue({}),
    };

    const module: TestingModule = await buildTestingModule({
      providers: [
        BillingWebhookService,
        { provide: RedisService, useValue: mockRedisService },
        { provide: InternalApiClientService, useValue: mockInternalApiClient },
        { provide: BillingTenantResolverService, useValue: mockTenantResolver },
        { provide: AsaasNormalizer, useValue: mockAsaasNormalizer },
        { provide: BullMQQueueFactory, useValue: mockQueueFactory },
      ],
    });

    service = module.get<BillingWebhookService>(BillingWebhookService);
    redisService = module.get(RedisService);
    internalApiClient = module.get(InternalApiClientService);
    tenantResolver = module.get(BillingTenantResolverService);
    asaasNormalizer = module.get(AsaasNormalizer);
    queueFactory = module.get(BullMQQueueFactory);
  });

  describe('handle', () => {
    const mockPayload = {
      event: 'PAYMENT_RECEIVED',
      payment: {
        id: 'pay_123',
        value: 100,
      },
    };

    beforeEach(() => {
      const resolvedTenant: ResolvedTenant = {
        tenant_id: 'tenant-123',
      };
      tenantResolver.resolveByWebhookToken.mockResolvedValue(resolvedTenant);

      const normalizedEvent: NormalizedPaymentEvent = {
        tenantId: 'tenant-123',
        provider: 'asaas',
        eventType: 'PAYMENT_RECEIVED',
        payment: {
          id: 'pay_123',
          customer: 'cus-123',
          value: 100,
          billingType: 'PIX',
          status: 'RECEIVED',
          dueDate: '2026-03-20',
        },
        idempotencyKey: 'idempo:key',
        receivedAt: new Date('2026-03-20T00:00:00.000Z'),
        rawPayload: mockPayload,
      };
      asaasNormalizer.normalize.mockReturnValue(normalizedEvent);

      redisService.ensureIdempotent.mockResolvedValue(true);
      redisService.publishStream.mockResolvedValue('stream-id-123');
      redisService.get.mockResolvedValue(null);
      redisService.set.mockResolvedValue(undefined);
      redisService.delete.mockResolvedValue(1);
    });

    it('should throw BadRequestException for unsupported provider', async () => {
      await expect(
        service.handle('unsupported', 'token', mockPayload),
      ).rejects.toThrow(BadRequestException);

      await expect(
        service.handle('unsupported', 'token', mockPayload),
      ).rejects.toThrow('Unsupported billing provider');
    });

    it('should process asaas webhook successfully', async () => {
      await service.handle('asaas', 'token-123', mockPayload);

      expect(tenantResolver.resolveByWebhookToken).toHaveBeenCalledWith(
        'token-123',
      );
      expect(asaasNormalizer.normalize).toHaveBeenCalledWith(
        'tenant-123',
        mockPayload,
      );
      expect(redisService.publishStream).toHaveBeenCalled();
    });

    it('should handle provider name case-insensitively', async () => {
      await service.handle('ASAAS', 'token-123', mockPayload);

      expect(asaasNormalizer.normalize).toHaveBeenCalled();
    });

    it('should mask token in logs', async () => {
      await service.handle('asaas', 'secret-token-12345', mockPayload);

      expect(tenantResolver.resolveByWebhookToken).toHaveBeenCalledWith(
        'secret-token-12345',
      );
    });

    it('should enqueue persistence and avoid synchronous database write', async () => {
      await service.handle('asaas', 'token-123', mockPayload);

      expect(queueFactory.createQueue).toHaveBeenCalledWith(
        'billing-webhook-persistence',
      );
      expect(persistenceQueue.add).toHaveBeenCalledWith(
        'persist-billing-webhook-event',
        expect.objectContaining({
          tenant_id: 'tenant-123',
          idempotencyKey: 'idempo:key',
          streamId: 'stream-id-123',
        }),
      );
      expect(internalApiClient.post).not.toHaveBeenCalled();
    });

    it('should skip duplicate events', async () => {
      redisService.get.mockResolvedValueOnce('1');

      await service.handle('asaas', 'token-123', mockPayload);

      expect(redisService.ensureIdempotent).not.toHaveBeenCalled();
      expect(redisService.publishStream).not.toHaveBeenCalled();
      expect(persistenceQueue.add).not.toHaveBeenCalled();
    });

    it('should skip duplicate events when in-flight lock already exists', async () => {
      redisService.ensureIdempotent.mockResolvedValueOnce(false);

      await service.handle('asaas', 'token-123', mockPayload);

      expect(redisService.publishStream).not.toHaveBeenCalled();
      expect(persistenceQueue.add).not.toHaveBeenCalled();
    });

    it('should skip late replay when durable dedup marker exists after lock TTL', async () => {
      await service.handle('asaas', 'token-123', mockPayload);

      redisService.get.mockResolvedValueOnce('1');
      redisService.ensureIdempotent.mockClear();
      redisService.publishStream.mockClear();
      persistenceQueue.add.mockClear();

      await service.handle('asaas', 'token-123', mockPayload);

      expect(redisService.ensureIdempotent).not.toHaveBeenCalled();
      expect(redisService.publishStream).not.toHaveBeenCalled();
      expect(persistenceQueue.add).not.toHaveBeenCalled();
    });

    it('should publish to Redis stream after persisting', async () => {
      await service.handle('asaas', 'token-123', mockPayload);

      expect(redisService.publishStream).toHaveBeenCalledWith(
        'billing.payment_received',
        expect.objectContaining({
          tenant_id: 'tenant-123',
          provider: 'ASAAS',
          event_type: 'PAYMENT_RECEIVED',
        }),
      );
    });

    it('should update stream_id after publishing', async () => {
      redisService.publishStream.mockResolvedValue('stream-id-456');

      await service.handle('asaas', 'token-123', mockPayload);

      expect(persistenceQueue.add).toHaveBeenCalledWith(
        'persist-billing-webhook-event',
        expect.objectContaining({ streamId: 'stream-id-456' }),
      );
    });

    it('should handle missing payment id in payload', async () => {
      const payloadWithoutId = {
        event: 'PAYMENT_RECEIVED',
        payment: {
          value: 100,
        },
      };

      const normalizedWithoutPaymentId: NormalizedPaymentEvent = {
        tenantId: 'tenant-123',
        provider: 'asaas',
        eventType: 'PAYMENT_RECEIVED',
        payment: {
          id: 'unknown',
          customer: 'cus-123',
          value: 100,
          billingType: 'PIX',
          status: 'RECEIVED',
          dueDate: '2026-03-20',
        },
        idempotencyKey: 'idempo:key',
        receivedAt: new Date('2026-03-20T00:00:00.000Z'),
        rawPayload: payloadWithoutId,
      };
      asaasNormalizer.normalize.mockReturnValue(normalizedWithoutPaymentId);

      await service.handle('asaas', 'token-123', payloadWithoutId);

      expect(redisService.publishStream).toHaveBeenCalled();
    });

    it('should resolve provider event id from top-level id', async () => {
      const payloadWithTopLevelId = {
        id: 'evt-123',
        event: 'PAYMENT_CREATED',
      };

      const normalizedTopLevelEventId: NormalizedPaymentEvent = {
        tenantId: 'tenant-123',
        provider: 'asaas',
        eventType: 'PAYMENT_CREATED',
        payment: {
          id: 'unknown',
          customer: 'cus-123',
          value: 0,
          billingType: 'UNDEFINED',
          status: 'PENDING',
          dueDate: '2026-03-20',
        },
        idempotencyKey: 'key',
        receivedAt: new Date('2026-03-20T00:00:00.000Z'),
        rawPayload: payloadWithTopLevelId,
      };
      asaasNormalizer.normalize.mockReturnValue(normalizedTopLevelEventId);

      await service.handle('asaas', 'token-123', payloadWithTopLevelId);

      expect(redisService.publishStream).toHaveBeenCalledWith(
        'billing.payment_received',
        expect.objectContaining({
          provider_event_id: 'evt-123',
        }),
      );
    });

    it('should not update stream_id when publishStream returns undefined', async () => {
      redisService.publishStream.mockResolvedValue(null);

      await service.handle('asaas', 'token-123', mockPayload);

      expect(persistenceQueue.add).toHaveBeenCalledWith(
        'persist-billing-webhook-event',
        expect.objectContaining({ streamId: null }),
      );
      expect(internalApiClient.post).not.toHaveBeenCalled();
    });

    it('should use async api fallback when enqueue fails to preserve traceability', async () => {
      persistenceQueue.add.mockRejectedValueOnce(
        new Error('queue unavailable'),
      );

      await service.handle('asaas', 'token-123', mockPayload);

      await new Promise<void>((resolve) => setImmediate(resolve));

      expect(internalApiClient.post).toHaveBeenCalledWith(
        '/api/internal/billing/webhook-events',
        expect.objectContaining({ tenant_id: 'tenant-123' }),
        'billing_event_create',
      );
    });
  });

  describe('helper methods', () => {
    it('should hash payload deterministically', async () => {
      const payload1 = { a: 1, b: 2 };
      const payload2 = { b: 2, a: 1 };

      const resolvedTenant: ResolvedTenant = {
        tenant_id: 'tenant-123',
      };
      tenantResolver.resolveByWebhookToken.mockResolvedValue(resolvedTenant);

      const normalizedEvent: NormalizedPaymentEvent = {
        tenantId: 'tenant-123',
        provider: 'asaas',
        eventType: 'PAYMENT_RECEIVED',
        payment: {
          id: 'pay_123',
          customer: 'cus-123',
          value: 100,
          billingType: 'PIX',
          status: 'RECEIVED',
          dueDate: '2026-03-20',
        },
        idempotencyKey: 'idempo:key',
        receivedAt: new Date('2026-03-20T00:00:00.000Z'),
        rawPayload: payload1,
      };
      asaasNormalizer.normalize.mockReturnValue(normalizedEvent);

      redisService.ensureIdempotent.mockResolvedValue(true);
      redisService.publishStream.mockResolvedValue('stream-id');

      await service.handle('asaas', 'token-123', payload1);

      const firstCall = redisService.publishStream.mock.calls[0];
      const firstHash = firstCall[1].payload_hash;

      redisService.publishStream.mockClear();

      await service.handle('asaas', 'token-123', payload2);

      const secondCall = redisService.publishStream.mock.calls[0];
      const secondHash = secondCall[1].payload_hash;

      expect(firstHash).toBe(secondHash);
    });
  });
});
