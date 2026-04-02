import { Test, TestingModule } from '@nestjs/testing';
import { RedisStreamsService } from './redis-streams.service';
import { RedisService } from './redis.service';
import { Redis as RedisClient } from 'ioredis';
import {
  createGatewayMessage,
  createSuccessResponse,
} from '../../common/interfaces';

describe('RedisStreamsService', () => {
  let service: RedisStreamsService;
  let redisService: jest.Mocked<RedisService>;

  beforeEach(async () => {
    const mockRedisService = {
      publishStream: jest.fn(),
      getClient: jest.fn(() => ({
        xgroup: jest.fn(),
        xreadgroup: jest.fn(),
        xack: jest.fn(),
      })),
    };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        RedisStreamsService,
        {
          provide: RedisService,
          useValue: mockRedisService,
        },
      ],
    }).compile();

    service = module.get<RedisStreamsService>(RedisStreamsService);
    redisService = module.get(RedisService);
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  const asRedisClient = (client: {
    xgroup?: jest.Mock;
    xreadgroup?: jest.Mock;
    xack?: jest.Mock;
  }): RedisClient => client as unknown as RedisClient;

  describe('publish', () => {
    it('should publish GatewayMessage to stream', async () => {
      redisService.publishStream.mockResolvedValue('1234567890-0');

      const message = createGatewayMessage({
        domain: 'ai',
        action: 'completion',
        provider: 'openai',
        payload: { prompt: 'test' },
      });

      const result = await service.publish('ai.run.request', message);

      expect(result).toBe('1234567890-0');
      expect(redisService.publishStream).toHaveBeenCalledWith(
        'ai.run.request',
        expect.objectContaining({
          correlation_id: message.correlationId,
          domain: 'ai',
          action: 'completion',
          provider: 'openai',
          payload: JSON.stringify({ prompt: 'test' }),
        }),
      );
    });

    it('should handle messages without metadata', async () => {
      redisService.publishStream.mockResolvedValue('1234567890-0');

      const message = createGatewayMessage({
        domain: 'chat',
        action: 'send',
        provider: 'zapi',
        payload: { text: 'hello' },
      });

      await service.publish('chat.outbound', message);

      expect(redisService.publishStream).toHaveBeenCalledWith(
        'chat.outbound',
        expect.objectContaining({
          metadata: '',
        }),
      );
    });

    it('should serialize metadata as JSON', async () => {
      redisService.publishStream.mockResolvedValue('1234567890-0');

      const message = createGatewayMessage({
        domain: 'ai',
        action: 'embed',
        provider: 'openai',
        payload: { text: 'test' },
        metadata: { model: 'text-embedding-ada-002' },
      });

      await service.publish('ai.embedding', message);

      expect(redisService.publishStream).toHaveBeenCalledWith(
        'ai.embedding',
        expect.objectContaining({
          metadata: JSON.stringify({ model: 'text-embedding-ada-002' }),
        }),
      );
    });
  });

  describe('publishResponse', () => {
    it('should publish GatewayResponse to stream', async () => {
      redisService.publishStream.mockResolvedValue('1234567890-0');

      const response = createSuccessResponse('corr-123', { result: 'success' });

      const result = await service.publishResponse('ai.run.response', response);

      expect(result).toBe('1234567890-0');
      expect(redisService.publishStream).toHaveBeenCalledWith(
        'ai.run.response',
        expect.objectContaining({
          correlation_id: 'corr-123',
          success: true,
          data: JSON.stringify({ result: 'success' }),
        }),
      );
    });

    it('should handle responses without data', async () => {
      redisService.publishStream.mockResolvedValue('1234567890-0');

      const response = createSuccessResponse('corr-123', undefined);

      await service.publishResponse('stream', response);

      expect(redisService.publishStream).toHaveBeenCalledWith(
        'stream',
        expect.objectContaining({
          data: '',
        }),
      );
    });

    it('should include processing time', async () => {
      redisService.publishStream.mockResolvedValue('1234567890-0');

      const response = createSuccessResponse('corr-123', { data: 'test' });
      (response as any).processingTimeMs = 150;

      await service.publishResponse('stream', response);

      expect(redisService.publishStream).toHaveBeenCalledWith(
        'stream',
        expect.objectContaining({
          processing_time_ms: 150,
        }),
      );
    });
  });

  describe('ensureConsumerGroup', () => {
    it('should create consumer group if it does not exist', async () => {
      const mockClient = {
        xgroup: jest.fn().mockResolvedValue('OK'),
      };
      redisService.getClient.mockReturnValue(asRedisClient(mockClient));

      await service.ensureConsumerGroup('test-stream', 'test-group');

      expect(mockClient.xgroup).toHaveBeenCalledWith(
        'CREATE',
        'test-stream',
        'test-group',
        '0',
        'MKSTREAM',
      );
    });

    it('should handle BUSYGROUP error gracefully', async () => {
      const mockClient = {
        xgroup: jest.fn().mockRejectedValue({
          message: 'BUSYGROUP Consumer Group name already exists',
        }),
      };
      redisService.getClient.mockReturnValue(asRedisClient(mockClient));

      await expect(
        service.ensureConsumerGroup('test-stream', 'test-group'),
      ).resolves.not.toThrow();
    });

    it('should throw other errors', async () => {
      const mockClient = {
        xgroup: jest.fn().mockRejectedValue(new Error('Connection error')),
      };
      redisService.getClient.mockReturnValue(asRedisClient(mockClient));

      await expect(
        service.ensureConsumerGroup('test-stream', 'test-group'),
      ).rejects.toThrow('Connection error');
    });
  });

  describe('readGroup', () => {
    it('should read messages from consumer group', async () => {
      const mockClient = {
        xreadgroup: jest
          .fn()
          .mockResolvedValue([
            [
              'test-stream',
              [
                [
                  '1234567890-0',
                  [
                    'correlation_id',
                    'corr-1',
                    'timestamp',
                    '2024-01-01T00:00:00.000Z',
                    'domain',
                    'ai',
                    'action',
                    'completion',
                    'provider',
                    'openai',
                    'payload',
                    '{"text":"hello"}',
                  ],
                ],
              ],
            ],
          ]),
      };
      redisService.getClient.mockReturnValue(asRedisClient(mockClient));

      const messages = await service.readGroup(
        'test-stream',
        'test-group',
        'consumer-1',
        10,
        2000,
      );

      expect(messages).toHaveLength(1);
      expect(messages[0].id).toBe('1234567890-0');
      expect(messages[0].message.domain).toBe('ai');
    });

    it('should return empty array when no messages', async () => {
      const mockClient = {
        xreadgroup: jest.fn().mockResolvedValue(null),
      };
      redisService.getClient.mockReturnValue(asRedisClient(mockClient));

      const messages = await service.readGroup(
        'test-stream',
        'test-group',
        'consumer-1',
      );

      expect(messages).toEqual([]);
    });

    it('should return empty array on error', async () => {
      const mockClient = {
        xreadgroup: jest.fn().mockRejectedValue(new Error('Read error')),
      };
      redisService.getClient.mockReturnValue(asRedisClient(mockClient));

      const messages = await service.readGroup(
        'test-stream',
        'test-group',
        'consumer-1',
      );

      expect(messages).toEqual([]);
    });

    it('should handle malformed stream data', async () => {
      const mockClient = {
        xreadgroup: jest.fn().mockResolvedValue([[]]),
      };
      redisService.getClient.mockReturnValue(asRedisClient(mockClient));

      const messages = await service.readGroup(
        'test-stream',
        'test-group',
        'consumer-1',
      );

      expect(messages).toEqual([]);
    });

    it('should parse metadata when present', async () => {
      const mockClient = {
        xreadgroup: jest
          .fn()
          .mockResolvedValue([
            [
              'test-stream',
              [
                [
                  '1234567890-0',
                  [
                    'correlation_id',
                    'corr-1',
                    'domain',
                    'chat',
                    'action',
                    'send',
                    'payload',
                    '{}',
                    'metadata',
                    '{"tenant_id":"t-1"}',
                  ],
                ],
              ],
            ],
          ]),
      };
      redisService.getClient.mockReturnValue(asRedisClient(mockClient));

      const messages = await service.readGroup(
        'test-stream',
        'test-group',
        'consumer-1',
      );

      expect(messages[0].message.metadata).toEqual({ tenant_id: 't-1' });
    });

    it('should handle invalid JSON payload gracefully', async () => {
      const mockClient = {
        xreadgroup: jest
          .fn()
          .mockResolvedValue([
            [
              'test-stream',
              [
                [
                  '1234567890-0',
                  [
                    'correlation_id',
                    'corr-1',
                    'domain',
                    'ai',
                    'action',
                    'test',
                    'payload',
                    'invalid-json',
                  ],
                ],
              ],
            ],
          ]),
      };
      redisService.getClient.mockReturnValue(asRedisClient(mockClient));

      const messages = await service.readGroup(
        'test-stream',
        'test-group',
        'consumer-1',
      );

      expect(messages[0].message.payload).toEqual({});
    });

    it('should handle invalid JSON metadata gracefully', async () => {
      const mockClient = {
        xreadgroup: jest
          .fn()
          .mockResolvedValue([
            [
              'test-stream',
              [
                [
                  '1234567890-0',
                  [
                    'correlation_id',
                    'corr-1',
                    'domain',
                    'ai',
                    'action',
                    'test',
                    'payload',
                    '{}',
                    'metadata',
                    'invalid-json',
                  ],
                ],
              ],
            ],
          ]),
      };
      redisService.getClient.mockReturnValue(asRedisClient(mockClient));

      const messages = await service.readGroup(
        'test-stream',
        'test-group',
        'consumer-1',
      );

      expect(messages[0].message.metadata).toBeUndefined();
    });
  });

  describe('ack', () => {
    it('should acknowledge message in stream', async () => {
      const mockClient = {
        xack: jest.fn().mockResolvedValue(1),
      };
      redisService.getClient.mockReturnValue(asRedisClient(mockClient));

      await service.ack('test-stream', 'test-group', '1234567890-0');

      expect(mockClient.xack).toHaveBeenCalledWith(
        'test-stream',
        'test-group',
        '1234567890-0',
      );
    });
  });

  describe('createSuccessResponse', () => {
    it('should create a success response', () => {
      const response = service.createSuccessResponse('corr-123', {
        data: 'test',
      });

      expect(response.correlationId).toBe('corr-123');
      expect(response.success).toBe(true);
      expect(response.data).toEqual({ data: 'test' });
    });

    it('should include processing time when provided', () => {
      const response = service.createSuccessResponse(
        'corr-123',
        { data: 'test' },
        150,
      );

      expect(response.processingTimeMs).toBe(150);
    });
  });

  describe('createErrorResponse', () => {
    it('should create an error response', () => {
      const response = service.createErrorResponse(
        'corr-123',
        'PROVIDER_ERROR',
        'Something went wrong',
      );

      expect(response.correlationId).toBe('corr-123');
      expect(response.success).toBe(false);
      expect(response.error).toBeDefined();
      expect(response.error?.message).toBe('Something went wrong');
    });

    it('should include processing time when provided', () => {
      const response = service.createErrorResponse(
        'corr-123',
        'TIMEOUT',
        'Request timed out',
        500,
      );

      expect(response.processingTimeMs).toBe(500);
    });

    it('should include details when provided', () => {
      const response = service.createErrorResponse(
        'corr-123',
        'VALIDATION_ERROR',
        'Invalid input',
        undefined,
        { field: 'email' },
      );

      expect(response.error?.details).toEqual({ field: 'email' });
    });
  });
});
