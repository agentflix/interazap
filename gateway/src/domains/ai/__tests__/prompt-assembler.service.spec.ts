/**
 * PromptAssemblerService Tests
 *
 * Covers cache hit/miss paths and snapshot short-circuit behavior.
 */

import { PromptAssemblerService } from '../services/prompt-assembler.service';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { InternalAiClientService } from '../services/internal-ai-client.service';
import { AiMetricsService } from '../services/ai-metrics.service';

const buildMocks = () => ({
  redis: {
    get: jest.fn<Promise<string | null>, [string]>(),
    set: jest.fn<Promise<void>, [string, string, number]>(),
  } as unknown as jest.Mocked<RedisService>,
  aiMetrics: {
    recordPromptCacheHit: jest.fn(),
  } as unknown as jest.Mocked<AiMetricsService>,
  internalAiClient: {
    fetchPrompt: jest.fn<Promise<string>, [string, string?]>(),
  } as unknown as jest.Mocked<InternalAiClientService>,
});

describe('PromptAssemblerService', () => {
  let service: PromptAssemblerService;
  let redis: ReturnType<typeof buildMocks>['redis'];
  let aiMetrics: ReturnType<typeof buildMocks>['aiMetrics'];
  let internalAiClient: ReturnType<typeof buildMocks>['internalAiClient'];

  const TENANT_ID = 'tenant-abc';
  const TRACE_ID = 'trace-001';
  const CACHE_KEY = `autopilot:prompt:${TENANT_ID}`;

  beforeEach(() => {
    const mocks = buildMocks();
    redis = mocks.redis;
    aiMetrics = mocks.aiMetrics;
    internalAiClient = mocks.internalAiClient;
    service = new PromptAssemblerService(redis, aiMetrics, internalAiClient);
  });

  it('should return cached prompt on cache hit', async () => {
    (redis.get as jest.Mock).mockResolvedValue('cached prompt');

    const result = await service.resolvePrompt(TENANT_ID, TRACE_ID);

    expect(result).toBe('cached prompt');
    expect(redis.get).toHaveBeenCalledWith(CACHE_KEY);
    expect(internalAiClient.fetchPrompt).not.toHaveBeenCalled();
    expect(aiMetrics.recordPromptCacheHit).toHaveBeenCalledWith(true);
  });

  it('should fetch and cache prompt on cache miss', async () => {
    (redis.get as jest.Mock).mockResolvedValue(null);
    (internalAiClient.fetchPrompt as jest.Mock).mockResolvedValue(
      'fresh prompt',
    );
    (redis.set as jest.Mock).mockResolvedValue(undefined);

    const result = await service.resolvePrompt(TENANT_ID, TRACE_ID);

    expect(result).toBe('fresh prompt');
    expect(internalAiClient.fetchPrompt).toHaveBeenCalledWith(
      TENANT_ID,
      TRACE_ID,
    );
    expect(redis.set).toHaveBeenCalledWith(CACHE_KEY, 'fresh prompt', 3600);
    expect(aiMetrics.recordPromptCacheHit).toHaveBeenCalledWith(false);
  });

  it('should return recent prompt snapshot without calling redis or backend', async () => {
    const result = await service.resolvePrompt(TENANT_ID, TRACE_ID, {
      prompt: 'snapshot prompt',
      hydrated_at: new Date(Date.now() - 15_000).toISOString(),
    });

    expect(result).toBe('snapshot prompt');
    expect(redis.get).not.toHaveBeenCalled();
    expect(redis.set).not.toHaveBeenCalled();
    expect(internalAiClient.fetchPrompt).not.toHaveBeenCalled();
    expect(aiMetrics.recordPromptCacheHit).toHaveBeenCalledWith(true);
  });
});
