import { Test, TestingModule } from '@nestjs/testing';
import { ChatWebhookController } from './chat-webhook.controller';
import { ChatWebhookService } from '../services/chat-webhook.service';
import { InstanceResolverService } from '../services/instance-resolver.service';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { MetricsService } from '../../../metrics/metrics.service';
import { WebhookEventDto } from '../dto/webhook-event.dto';
import { Request } from 'express';
import { ThrottlerGuard } from '@nestjs/throttler';

describe('ChatWebhookController', () => {
  let controller: ChatWebhookController;
  let chatWebhookService: jest.Mocked<ChatWebhookService>;
  let redisService: jest.Mocked<RedisService>;
  let metricsService: jest.Mocked<MetricsService>;

  beforeEach(async () => {
    const mockChatWebhookService = {
      handle: jest.fn().mockResolvedValue(undefined),
    };
    const mockInstanceResolverService = {
      resolveByWebhookToken: jest.fn().mockResolvedValue({
        tenant_id: 'tenant-123',
        instance_id: 'instance-123',
        provider: 'zapi',
      }),
    };
    const mockRedisService = {
      ensureIdempotent: jest.fn().mockResolvedValue(true),
    };
    const mockMetricsService = {
      recordWebhookAck: jest.fn(),
    };

    const module: TestingModule = await Test.createTestingModule({
      controllers: [ChatWebhookController],
      providers: [
        {
          provide: ChatWebhookService,
          useValue: mockChatWebhookService,
        },
        {
          provide: InstanceResolverService,
          useValue: mockInstanceResolverService,
        },
        {
          provide: RedisService,
          useValue: mockRedisService,
        },
        {
          provide: MetricsService,
          useValue: mockMetricsService,
        },
      ],
    })
      .overrideGuard(ThrottlerGuard)
      .useValue({ canActivate: () => true })
      .compile();

    controller = module.get<ChatWebhookController>(ChatWebhookController);
    chatWebhookService = module.get(ChatWebhookService);
    redisService = module.get(RedisService);
    metricsService = module.get(MetricsService);
  });

  const buildRequest = (body: Record<string, unknown>): Request =>
    ({ body }) as unknown as Request;

  describe('handleWebhook', () => {
    it('should continue processing and mark first_seen when pre-ack idempotency returns true', async () => {
      const mockRequest = buildRequest({
        event_type: 'message.new',
        message: { id: 'msg-1', text: 'Hello' },
      });

      const payload: WebhookEventDto = {
        event_type: 'message.new',
      };

      chatWebhookService.handle.mockResolvedValue(undefined);

      const result = await controller.handleWebhook(
        'zapi',
        'token-123',
        payload,
        mockRequest,
      );

      expect(result).toEqual({ success: true });
      expect(redisService.ensureIdempotent).toHaveBeenCalled();
      expect(chatWebhookService.handle).toHaveBeenCalledWith(
        'zapi',
        'token-123',
        expect.any(Object),
        null,
      );
      expect(metricsService.recordWebhookAck).toHaveBeenCalledWith(
        'zapi',
        'deferred',
        'first_seen',
        expect.any(Number),
      );
    });

    it('should preserve raw body', async () => {
      const mockRequest = buildRequest({
        event_type: 'message.new',
        custom_field: 'should-be-preserved',
      });

      const payload: WebhookEventDto = {
        event_type: 'message.new',
      };

      chatWebhookService.handle.mockResolvedValue(undefined);

      await controller.handleWebhook(
        'uazapi',
        'token-456',
        payload,
        mockRequest,
      );

      expect(chatWebhookService.handle).toHaveBeenCalled();
    });

    it('should handle different providers', async () => {
      const mockRequest = buildRequest({ event_type: 'connection' });
      const payload: WebhookEventDto = { event_type: 'connection' };

      chatWebhookService.handle.mockResolvedValue(undefined);

      await controller.handleWebhook(
        'uazapi',
        'token-789',
        payload,
        mockRequest,
      );

      expect(chatWebhookService.handle).toHaveBeenCalledWith(
        'uazapi',
        'token-789',
        expect.any(Object),
        null,
      );
    });

    it('should short-circuit duplicate non-connection webhook in controller path', async () => {
      redisService.ensureIdempotent.mockResolvedValue(false);

      const mockRequest = buildRequest({
        event_type: 'messages',
        message: { id: 'msg-duplicate' },
      });

      const payload: WebhookEventDto = {
        event_type: 'messages',
        message: { id: 'msg-duplicate' },
      };

      const result = await controller.handleWebhook(
        'zapi',
        'token-123',
        payload,
        mockRequest,
      );

      expect(result).toEqual({ success: true });
      expect(chatWebhookService.handle).not.toHaveBeenCalled();
      expect(metricsService.recordWebhookAck).toHaveBeenCalledWith(
        'zapi',
        'deferred',
        'duplicate',
        expect.any(Number),
      );
    });

    it('should continue processing and mark idempotency_error when pre-ack idempotency times out', async () => {
      jest.useFakeTimers();
      redisService.ensureIdempotent.mockImplementation(
        () => new Promise<boolean>(() => undefined),
      );

      const mockRequest = buildRequest({
        event_type: 'messages',
        message: { id: 'msg-timeout' },
      });

      const payload: WebhookEventDto = {
        event_type: 'messages',
        message: { id: 'msg-timeout' },
      };

      const handlePromise = controller.handleWebhook(
        'zapi',
        'token-123',
        payload,
        mockRequest,
      );

      await jest.advanceTimersByTimeAsync(150);
      const result = await handlePromise;

      expect(result).toEqual({ success: true });
      expect(chatWebhookService.handle).toHaveBeenCalledWith(
        'zapi',
        'token-123',
        expect.any(Object),
        null,
      );
      expect(metricsService.recordWebhookAck).toHaveBeenCalledWith(
        'zapi',
        'deferred',
        'idempotency_error',
        expect.any(Number),
      );

      jest.useRealTimers();
    });

    it('should bypass controller dedupe for connection events', async () => {
      const mockRequest = buildRequest({
        event_type: 'connection.update',
        instance: { status: 'connected' },
      });

      const payload: WebhookEventDto = {
        event_type: 'connection.update',
      };

      await controller.handleWebhook('zapi', 'token-123', payload, mockRequest);

      expect(redisService.ensureIdempotent).not.toHaveBeenCalled();
      expect(chatWebhookService.handle).toHaveBeenCalled();
      expect(metricsService.recordWebhookAck).toHaveBeenCalledWith(
        'zapi',
        'deferred',
        'connection_bypass',
        expect.any(Number),
      );
    });

    it('should use edit event id in pre-ack idempotency key for edited messages', async () => {
      const mockRequest = buildRequest({
        event_type: 'messages',
        message: {
          id: 'edit-event-1',
          edited: 'msg-original-1',
          text: 'Texto editado',
        },
      });

      const payload: WebhookEventDto = {
        event_type: 'messages',
      };

      await controller.handleWebhook(
        'uazapi',
        'token-123',
        payload,
        mockRequest,
      );

      expect(redisService.ensureIdempotent).toHaveBeenCalledWith(
        expect.stringContaining(':messages:edit_event:edit-event-1'),
        120,
      );
    });

    it('should generate different edit signature keys for different edited payloads without event id', async () => {
      const firstRequest = buildRequest({
        event_type: 'messages',
        message: {
          edited: 'msg-original-1',
          text: 'Texto v1',
          timestamp: '1706550000',
        },
      });

      const secondRequest = buildRequest({
        event_type: 'messages',
        message: {
          edited: 'msg-original-1',
          text: 'Texto v2',
          timestamp: '1706550001',
        },
      });

      const payload: WebhookEventDto = {
        event_type: 'messages',
      };

      await controller.handleWebhook(
        'uazapi',
        'token-123',
        payload,
        firstRequest,
      );
      await controller.handleWebhook(
        'uazapi',
        'token-123',
        payload,
        secondRequest,
      );

      const firstKey = redisService.ensureIdempotent.mock.calls[0]?.[0];
      const secondKey = redisService.ensureIdempotent.mock.calls[1]?.[0];

      expect(firstKey).toContain(':messages:edit_sig:');
      expect(secondKey).toContain(':messages:edit_sig:');
      expect(firstKey).not.toEqual(secondKey);
    });
  });
});
