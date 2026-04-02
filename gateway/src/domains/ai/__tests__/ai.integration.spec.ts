/**
 * AI Integration Tests
 *
 * Testes de integração do fluxo completo via Redis Streams.
 */

import { Test, TestingModule } from '@nestjs/testing';
import { ConfigModule } from '@nestjs/config';
import { AIModule } from '../ai.module';
import { RedisModule } from '../../../infrastructure/redis/redis.module';
import { RedisStreamsService } from '../../../infrastructure/redis/redis-streams.service';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { AIProviderFactory } from '../providers/ai-provider.factory';
import { AICompletionRequest } from '../interfaces/ai-completion-request.dto';
import { createGatewayMessage } from '../../../common/interfaces/gateway-message.interface';
import { GatewayResponse } from '../../../common/interfaces/gateway-response.interface';
import { GatewayConfigService } from '../../../shared/services/gateway-config.service';
import { SharedModule } from '../../../shared/shared.module';

const INTEGRATION_TIMEOUT_MS = process.env.CI ? 60_000 : 120_000;
jest.setTimeout(INTEGRATION_TIMEOUT_MS);

class InMemoryRedisClient {
  private streams = new Map<string, Array<{ id: string; fields: string[] }>>();
  private consumerGroups = new Map<string, Set<string>>();
  private sequence = 0;

  xadd(
    stream: string,
    _maxLenKeyword: string,
    _approx: string,
    _limit: string,
    _idSpecifier: string,
    ...entries: string[]
  ): Promise<string> {
    const id = `${Date.now()}-${++this.sequence}`;
    const normalized: string[] = [];
    for (let i = 0; i < entries.length; i += 2) {
      const key = entries[i];
      const value = entries[i + 1] ?? '';
      normalized.push(key, value);
    }
    const current = this.streams.get(stream) ?? [];
    current.push({ id, fields: normalized });
    this.streams.set(stream, current);
    return Promise.resolve(id);
  }

  xgroup(command: string, stream: string, group: string): Promise<'OK'> {
    if (command !== 'CREATE') {
      return Promise.resolve('OK');
    }

    const groups = this.consumerGroups.get(stream) ?? new Set<string>();
    if (groups.has(group)) {
      throw new Error('BUSYGROUP Consumer Group name already exists');
    }

    groups.add(group);
    this.consumerGroups.set(stream, groups);
    if (!this.streams.has(stream)) {
      this.streams.set(stream, []);
    }

    return Promise.resolve('OK');
  }

  xreadgroup(): Promise<null> {
    return Promise.resolve(null);
  }

  xack(): Promise<number> {
    return Promise.resolve(1);
  }

  quit(): Promise<'OK'> {
    return Promise.resolve('OK');
  }
}

class FakeRedisService {
  private readonly client = new InMemoryRedisClient();

  getClient() {
    return this.client as unknown as {
      xadd: InMemoryRedisClient['xadd'];
      xgroup: InMemoryRedisClient['xgroup'];
      xreadgroup: InMemoryRedisClient['xreadgroup'];
      xack: InMemoryRedisClient['xack'];
      quit: InMemoryRedisClient['quit'];
    };
  }

  getPubSubClient() {
    return this.getClient();
  }

  publish(): Promise<number> {
    return Promise.resolve(1);
  }

  publishStream(
    stream: string,
    payload: Record<string, unknown>,
  ): Promise<string> {
    const serialized = Object.entries(payload).flatMap(([key, value]) => {
      if (typeof value === 'string') {
        return [key, value];
      }
      if (value === undefined) {
        return [key, ''];
      }
      return [key, JSON.stringify(value)];
    });

    return this.client.xadd(stream, 'MAXLEN', '~', '10000', '*', ...serialized);
  }

  async onModuleDestroy(): Promise<void> {
    await this.client.quit();
  }
}

describe('AI Integration Tests', () => {
  let moduleRef: TestingModule | null = null;
  let redisStreams: RedisStreamsService;
  let providerFactory: AIProviderFactory;

  const REQUEST_STREAM = 'ai.run.request';

  beforeAll(async () => {
    moduleRef = await Test.createTestingModule({
      imports: [
        ConfigModule.forRoot({
          isGlobal: true,
          envFilePath: '.env.test',
        }),
        RedisModule,
        SharedModule,
        AIModule,
      ],
    })
      .overrideProvider(RedisService)
      .useClass(FakeRedisService)
      .overrideProvider(GatewayConfigService)
      .useValue({ isTestEnvironment: jest.fn().mockReturnValue(true) })
      .compile();

    redisStreams = moduleRef.get<RedisStreamsService>(RedisStreamsService);
    providerFactory = moduleRef.get<AIProviderFactory>(AIProviderFactory);

    await moduleRef.init();
  });

  afterAll(async () => {
    if (moduleRef) {
      await moduleRef.close();
      moduleRef = null;
    }
  });

  describe('Redis Streams flow', () => {
    it('should have provider factory with openai registered', () => {
      expect(providerFactory.hasProvider('openai')).toBe(true);
    });

    it('should list available providers', () => {
      const providers = providerFactory.listProviders();
      expect(providers).toContain('openai');
    });

    it('should publish message to request stream', async () => {
      const correlationId = `test-${Date.now()}`;
      const message = createGatewayMessage<AICompletionRequest>({
        correlationId,
        domain: 'ai',
        action: 'completion',
        provider: 'openai',
        payload: {
          messages: [{ role: 'user', content: 'Test message' }],
        },
      });

      const result = await redisStreams.publish(REQUEST_STREAM, message);

      expect(result).toBeTruthy();
      expect(typeof result).toBe('string');
    });

    it('should ensure consumer group exists', async () => {
      await expect(
        redisStreams.ensureConsumerGroup(REQUEST_STREAM, 'test-group'),
      ).resolves.not.toThrow();
    });
  });

  describe('Provider health checks', () => {
    it('should check health of all providers', async () => {
      const health = await providerFactory.checkHealth();

      expect(health).toBeInstanceOf(Map);
      expect(health.has('openai')).toBe(true);
      // Health may be false if API key is not configured in test env
    });
  });

  describe('Error handling', () => {
    it('should throw for unknown provider', () => {
      expect(() => providerFactory.getProvider('nonexistent')).toThrow(
        "Unknown AI provider: 'nonexistent'",
      );
    });
  });
});

/**
 * Unit tests that mock Redis (always run)
 */
describe('AI Integration Unit Tests (Mocked)', () => {
  let redisStreams: RedisStreamsService;
  let redisService: jest.Mocked<RedisService>;

  beforeEach(async () => {
    const mockRedisClient = {
      xadd: jest.fn().mockResolvedValue('1234567890-0'),
      xgroup: jest.fn().mockResolvedValue('OK'),
      xreadgroup: jest.fn().mockResolvedValue(null),
      xack: jest.fn().mockResolvedValue(1),
    };

    redisService = {
      getClient: jest.fn().mockReturnValue(mockRedisClient),
      publishStream: jest.fn().mockResolvedValue('1234567890-0'),
    } as any;

    const module = await Test.createTestingModule({
      providers: [
        RedisStreamsService,
        {
          provide: RedisService,
          useValue: redisService,
        },
      ],
    }).compile();

    redisStreams = module.get<RedisStreamsService>(RedisStreamsService);
  });

  describe('publish', () => {
    it('should serialize message correctly', async () => {
      const message = createGatewayMessage<AICompletionRequest>({
        correlationId: 'test-123',
        domain: 'ai',
        action: 'completion',
        provider: 'openai',
        payload: {
          messages: [{ role: 'user', content: 'Hello' }],
        },
        metadata: { tenantId: 'tenant-1' },
      });

      await redisStreams.publish('test-stream', message);

      expect(redisService.publishStream).toHaveBeenCalledWith(
        'test-stream',
        expect.objectContaining({
          correlation_id: 'test-123',
          domain: 'ai',
          action: 'completion',
          provider: 'openai',
        }),
      );
    });
  });

  describe('publishResponse', () => {
    it('should serialize response correctly', async () => {
      const response: GatewayResponse<{ content: string }> = {
        correlationId: 'resp-123',
        timestamp: new Date().toISOString(),
        success: true,
        data: { content: 'Hello world' },
        processingTimeMs: 150,
      };

      await redisStreams.publishResponse('response-stream', response);

      expect(redisService.publishStream).toHaveBeenCalledWith(
        'response-stream',
        expect.objectContaining({
          correlation_id: 'resp-123',
          success: true,
        }),
      );
    });
  });

  describe('createSuccessResponse', () => {
    it('should create success response with correct structure', () => {
      const response = redisStreams.createSuccessResponse(
        'corr-id',
        { result: 'ok' },
        100,
      );

      expect(response.correlationId).toBe('corr-id');
      expect(response.success).toBe(true);
      expect(response.data).toEqual({ result: 'ok' });
      expect(response.processingTimeMs).toBe(100);
      expect(response.error).toBeUndefined();
    });
  });

  describe('createErrorResponse', () => {
    it('should create error response with correct structure', () => {
      const response = redisStreams.createErrorResponse(
        'corr-id',
        'PROVIDER_TIMEOUT',
        'Request timed out',
        200,
      );

      expect(response.correlationId).toBe('corr-id');
      expect(response.success).toBe(false);
      expect(response.data).toBeUndefined();
      expect(response.error?.code).toBe('PROVIDER_TIMEOUT');
      expect(response.error?.message).toBe('Request timed out');
      expect(response.processingTimeMs).toBe(200);
    });
  });
});
