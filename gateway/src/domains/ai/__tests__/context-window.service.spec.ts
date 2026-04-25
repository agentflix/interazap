/**
 * ContextWindowService Tests
 *
 * Boundary tests for context resolution logic:
 * cache hit → return parsed JSON, cache miss → fetch + cache + return.
 */

import { ContextWindowService } from '../services/context-window.service';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { InternalAiClientService } from '../services/internal-ai-client.service';
import { AiMetricsService } from '../services/ai-metrics.service';

const buildMocks = () => ({
  redis: {
    get: jest.fn<Promise<string | null>, [string]>(),
    set: jest.fn<Promise<void>, [string, string, number]>(),
  } as unknown as jest.Mocked<RedisService>,
  aiMetrics: {
    recordContextCacheHit: jest.fn(),
    recordSnapshotResolution: jest.fn(),
  } as unknown as jest.Mocked<AiMetricsService>,
  internalAiClient: {
    fetchContext: jest.fn<
      Promise<Record<string, unknown>>,
      [string, string?]
    >(),
  } as unknown as jest.Mocked<InternalAiClientService>,
});

describe('ContextWindowService', () => {
  let service: ContextWindowService;
  let redis: ReturnType<typeof buildMocks>['redis'];
  let aiMetrics: ReturnType<typeof buildMocks>['aiMetrics'];
  let internalAiClient: ReturnType<typeof buildMocks>['internalAiClient'];

  beforeEach(() => {
    const mocks = buildMocks();
    redis = mocks.redis;
    aiMetrics = mocks.aiMetrics;
    internalAiClient = mocks.internalAiClient;
    service = new ContextWindowService(redis, aiMetrics, internalAiClient);
  });

  const TICKET_ID = 'ticket-abc';
  const TRACE_ID = 'trace-001';
  const CACHE_KEY = `autopilot:context:${TICKET_ID}`;

  describe('cache HIT', () => {
    it('should return parsed JSON from cache without calling backend', async () => {
      const cached = { summary: 'previous conversation' };
      (redis.get as jest.Mock).mockResolvedValue(JSON.stringify(cached));

      const result = await service.resolveContext(TICKET_ID, TRACE_ID);

      expect(result).toEqual(cached);
      expect(redis.get).toHaveBeenCalledWith(CACHE_KEY);
      expect(internalAiClient.fetchContext).not.toHaveBeenCalled();
      expect(aiMetrics.recordContextCacheHit).toHaveBeenCalledWith(true);
    });

    it('should return {} when cached value is invalid JSON', async () => {
      (redis.get as jest.Mock).mockResolvedValue('not-valid-json{{');

      const result = await service.resolveContext(TICKET_ID);

      expect(result).toEqual({});
      expect(aiMetrics.recordContextCacheHit).toHaveBeenCalledWith(true);
    });
  });

  describe('cache MISS', () => {
    it('should fetch from backend, cache the result and return it', async () => {
      const fetched = { messages: [{ role: 'user', content: 'hello' }] };
      (redis.get as jest.Mock).mockResolvedValue(null);
      (internalAiClient.fetchContext as jest.Mock).mockResolvedValue(fetched);
      (redis.set as jest.Mock).mockResolvedValue(undefined);

      const result = await service.resolveContext(TICKET_ID, TRACE_ID);

      expect(result).toEqual(fetched);
      expect(internalAiClient.fetchContext).toHaveBeenCalledWith(
        TICKET_ID,
        TRACE_ID,
      );
      expect(redis.set).toHaveBeenCalledWith(
        CACHE_KEY,
        JSON.stringify(fetched),
        1800,
      );
      expect(aiMetrics.recordContextCacheHit).toHaveBeenCalledWith(false);
    });

    it('should not call redis.set when traceId is omitted', async () => {
      (redis.get as jest.Mock).mockResolvedValue(null);
      (internalAiClient.fetchContext as jest.Mock).mockResolvedValue({});
      (redis.set as jest.Mock).mockResolvedValue(undefined);

      await service.resolveContext(TICKET_ID);

      expect(internalAiClient.fetchContext).toHaveBeenCalledWith(
        TICKET_ID,
        undefined,
      );
    });
  });

  describe('snapshot short-circuit', () => {
    it('should return recent snapshot context without calling redis or backend', async () => {
      const snapshotContext = {
        ticket: { id: TICKET_ID },
        last_messages: [{ content: 'snapshot-message' }],
      };

      const result = await service.resolveContext(TICKET_ID, TRACE_ID, {
        context: snapshotContext,
        hydrated_at: new Date(Date.now() - 10_000).toISOString(),
      });

      expect(result).toEqual(snapshotContext);
      expect(redis.get).not.toHaveBeenCalled();
      expect(redis.set).not.toHaveBeenCalled();
      expect(internalAiClient.fetchContext).not.toHaveBeenCalled();
    });
  });
});
