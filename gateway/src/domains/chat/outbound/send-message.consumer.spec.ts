import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import { SendMessageConsumer } from './send-message.consumer';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { SendMessageService, SendResult } from './send-message.service';
import { StreamDlqService } from '../../../shared/services/queue/stream-dlq.service';
import { GatewayConfigService } from '../../../shared/services/gateway-config.service';

describe('SendMessageConsumer', () => {
  let consumer: SendMessageConsumer;
  let mockRedisService: jest.Mocked<RedisService>;
  let mockSendMessageService: jest.Mocked<SendMessageService>;
  let mockStreamDlqService: jest.Mocked<StreamDlqService>;
  let mockRedisClient: {
    xgroup: jest.Mock;
    xack: jest.Mock;
  };
  const originalConsumersEnabled = process.env.CONSUMERS_ENABLED;

  const createStreamMessage = (fields: Record<string, string>) => ({
    id: '1234567890-0',
    fields,
  });

  beforeEach(async () => {
    process.env.CONSUMERS_ENABLED = 'true';
    mockRedisClient = {
      xgroup: jest.fn(),
      xack: jest.fn().mockResolvedValue(1),
    };

    mockRedisService = {
      getClient: jest.fn().mockReturnValue(mockRedisClient),
      publishStream: jest.fn().mockResolvedValue('stream-id'),
      xreadBlock: jest.fn().mockResolvedValue([]),
    } as unknown as jest.Mocked<RedisService>;

    mockSendMessageService = {
      send: jest.fn(),
    } as unknown as jest.Mocked<SendMessageService>;

    mockStreamDlqService = {
      capture: jest.fn().mockResolvedValue(undefined),
      getPending: jest.fn().mockResolvedValue([]),
      getSize: jest.fn().mockResolvedValue(0),
      retry: jest.fn().mockResolvedValue(true),
    } as unknown as jest.Mocked<StreamDlqService>;

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        SendMessageConsumer,
        {
          provide: ConfigService,
          useValue: {
            get: jest.fn((key: string) => {
              if (key === 'CONSUMERS_ENABLED') {
                return 'true';
              }

              return undefined;
            }),
          },
        },
        {
          provide: GatewayConfigService,
          useValue: {
            isTestEnvironment: jest.fn().mockReturnValue(true),
            chatOutboundStream: 'chat.outbound_message',
            chatOutboundStatusStream: 'chat.outbound_message_status',
          },
        },
        { provide: RedisService, useValue: mockRedisService },
        { provide: SendMessageService, useValue: mockSendMessageService },
        { provide: StreamDlqService, useValue: mockStreamDlqService },
      ],
    }).compile();

    consumer = module.get<SendMessageConsumer>(SendMessageConsumer);
    jest.spyOn(consumer as any, 'startConsuming').mockImplementation(() => {});
  });

  afterEach(async () => {
    await consumer.onModuleDestroy();
    jest.useRealTimers();
    if (originalConsumersEnabled === undefined) {
      delete process.env.CONSUMERS_ENABLED;
    } else {
      process.env.CONSUMERS_ENABLED = originalConsumersEnabled;
    }
  });

  describe('onModuleInit', () => {
    it('should create consumer group on init', async () => {
      mockRedisClient.xgroup.mockResolvedValue('OK');

      await consumer.onModuleInit();

      expect(mockRedisClient.xgroup).toHaveBeenCalledWith(
        'CREATE',
        'chat.outbound_message',
        'gateway-outbound',
        '0',
        'MKSTREAM',
      );
    });

    it('should handle existing consumer group', async () => {
      const busyGroupError = new Error(
        'BUSYGROUP Consumer Group already exists',
      );
      mockRedisClient.xgroup.mockRejectedValue(busyGroupError);

      await expect(consumer.onModuleInit()).resolves.not.toThrow();
    });
  });

  describe('message processing', () => {
    it('should parse message fields correctly', async () => {
      const streamMessage = createStreamMessage({
        tenant_id: 'tenant-123',
        instance_id: 'instance-456',
        provider: 'uazapi',
        instance_token: 'token-abc',
        type: 'text',
        to: '5511999999999',
        text: 'Hello World',
        correlation_id: 'corr-789',
      });

      const mockResult: SendResult = {
        success: true,
        messageId: 'msg-123',
        attempts: 1,
        processingTimeMs: 50,
      };
      mockSendMessageService.send.mockResolvedValue(mockResult);

      await (consumer as any).processMessageWithRetry(streamMessage);

      expect(mockSendMessageService.send).toHaveBeenCalledWith(
        expect.objectContaining({
          tenantId: 'tenant-123',
          instanceId: 'instance-456',
          provider: 'uazapi',
          instanceToken: 'token-abc',
          type: 'text',
          to: '5511999999999',
          text: 'Hello World',
          correlationId: 'corr-789',
        }),
      );
    });

    it('should acknowledge message after processing', async () => {
      const streamMessage = createStreamMessage({
        tenant_id: 'tenant-123',
        type: 'text',
        to: '5511999999999',
      });

      mockSendMessageService.send.mockResolvedValue({
        success: true,
        messageId: 'msg-123',
        attempts: 1,
        processingTimeMs: 50,
      });

      await (consumer as any).processMessageWithRetry(streamMessage);

      expect(mockRedisClient.xack).toHaveBeenCalledWith(
        'chat.outbound_message',
        'gateway-outbound',
        '1234567890-0',
      );
    });

    it('should publish result to status stream', async () => {
      const streamMessage = createStreamMessage({
        tenant_id: 'tenant-123',
        instance_id: 'instance-456',
        type: 'text',
        to: '5511999999999',
      });

      mockSendMessageService.send.mockResolvedValue({
        success: true,
        messageId: 'msg-123',
        attempts: 1,
        processingTimeMs: 50,
      });

      await (consumer as any).processMessageWithRetry(streamMessage);

      expect(mockRedisService.publishStream).toHaveBeenCalledWith(
        'chat.outbound_message_status',
        expect.objectContaining({
          original_message_id: '1234567890-0',
          tenant_id: 'tenant-123',
          instance_id: 'instance-456',
          success: true,
          message_id: 'msg-123',
          attempts: 1,
        }),
      );
    });
  });

  describe('DLQ handling', () => {
    it('should requeue when retries remain', async () => {
      jest.useFakeTimers();

      const streamMessage = createStreamMessage({
        tenant_id: 'tenant-123',
        type: 'text',
        to: '5511999999999',
        _retry_count: '0',
      });

      mockSendMessageService.send.mockResolvedValue({
        success: false,
        error: 'Provider unavailable',
        attempts: 1,
        processingTimeMs: 10,
      });

      await (consumer as any).processMessageWithRetry(streamMessage);

      jest.runOnlyPendingTimers();

      expect(mockStreamDlqService.capture).not.toHaveBeenCalled();
      expect(mockRedisService.publishStream).toHaveBeenCalledWith(
        'chat.outbound_message',
        expect.objectContaining({
          _retry_count: '1',
        }),
      );
    });

    it('should send to DLQ after max retries', async () => {
      const streamMessage = createStreamMessage({
        tenant_id: 'tenant-123',
        type: 'text',
        to: '5511999999999',
        _retry_count: '5',
      });

      mockSendMessageService.send.mockResolvedValue({
        success: false,
        error: 'Provider unavailable',
        attempts: 1,
        processingTimeMs: 10,
      });

      await (consumer as any).processMessageWithRetry(streamMessage);

      expect(mockStreamDlqService.capture).toHaveBeenCalledWith(
        'chat.outbound_message',
        '1234567890-0',
        expect.any(Object),
        'Provider unavailable',
        6,
        expect.objectContaining({ suffix: '.dlq' }),
      );

      expect(mockRedisService.publishStream).toHaveBeenCalledWith(
        'chat.outbound_message_status',
        expect.objectContaining({
          success: false,
          error: 'Provider unavailable',
        }),
      );
    });
  });

  describe('blocking reads', () => {
    it('should use blocking read helper', async () => {
      await (consumer as any).blockingRead();

      expect(mockRedisService.xreadBlock).toHaveBeenCalledWith(
        'chat.outbound_message',
        'gateway-outbound',
        expect.stringContaining('gateway-'),
        5000,
        10,
      );
    });

    it('should avoid tight polling when idle', async () => {
      (consumer as any).isRunning = true;
      mockRedisService.xreadBlock.mockImplementation(() => {
        (consumer as any).isRunning = false;
        return Promise.resolve([]);
      });

      await (consumer as any).consumeLoop();

      expect(mockRedisService.xreadBlock).toHaveBeenCalledTimes(1);
    });
  });
});
