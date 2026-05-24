import { Test, TestingModule } from '@nestjs/testing';
import { Registry } from 'prom-client';
import { GatewayCacheService } from './gateway-cache.service';
import { RedisService } from '../redis/redis.service';
import { MetricsService } from '../../metrics/metrics.service';
import { CacheStrategies } from './gateway-cache.types';

describe('GatewayCacheService', () => {
  let service: GatewayCacheService;
  let registry: Registry;
  let redisGet: jest.Mock;
  let redisSet: jest.Mock;
  let redisDelete: jest.Mock;
  let pubSubOn: jest.Mock;
  let pubSubSubscribe: jest.Mock;

  const STRATEGY = CacheStrategies.HOT;
  const OP = 'test-op';

  beforeEach(async () => {
    registry = new Registry();

    redisGet = jest.fn().mockResolvedValue(null);
    redisSet = jest.fn().mockResolvedValue(undefined);
    redisDelete = jest.fn().mockResolvedValue(1);
    pubSubOn = jest.fn();
    pubSubSubscribe = jest.fn();

    const redisService = {
      get: redisGet,
      set: redisSet,
      delete: redisDelete,
      getPubSubClient: jest.fn().mockReturnValue({
        subscribe: pubSubSubscribe,
        on: pubSubOn,
      }),
    };

    const metricsService = {
      getRegistry: jest.fn().mockReturnValue(registry),
    };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        GatewayCacheService,
        { provide: RedisService, useValue: redisService },
        { provide: MetricsService, useValue: metricsService },
      ],
    }).compile();

    service = module.get<GatewayCacheService>(GatewayCacheService);
    service.onModuleInit();
  });

  describe('getOrFetch()', () => {
    it('L1 hit — fetcher não chamado', async () => {
      const fetcher = jest.fn().mockResolvedValue({ id: '1' });

      // Prime L1 via miss
      redisGet.mockResolvedValueOnce(null);
      await service.getOrFetch('key:1', fetcher, STRATEGY, OP);
      fetcher.mockClear();
      redisGet.mockClear();

      // Second call — L1 hit
      const result = await service.getOrFetch('key:1', fetcher, STRATEGY, OP);

      expect(result).toEqual({ id: '1' });
      expect(fetcher).not.toHaveBeenCalled();
      expect(redisGet).not.toHaveBeenCalled();
    });

    it('L1 miss + L2 hit — popula L1, fetcher não chamado', async () => {
      const cached = { id: '2' };
      redisGet.mockResolvedValueOnce(JSON.stringify(cached));

      const fetcher = jest.fn();
      const result = await service.getOrFetch('key:2', fetcher, STRATEGY, OP);

      expect(result).toEqual(cached);
      expect(fetcher).not.toHaveBeenCalled();
    });

    it('L1 miss + L2 miss — fetcher chamado, ambas as camadas populadas', async () => {
      const fetched = { id: '3' };
      redisGet.mockResolvedValueOnce(null);
      const fetcher = jest.fn().mockResolvedValue(fetched);

      const result = await service.getOrFetch('key:3', fetcher, STRATEGY, OP);

      expect(result).toEqual(fetched);
      expect(fetcher).toHaveBeenCalledTimes(1);
      expect(redisSet).toHaveBeenCalledWith(
        'gw:cache:key:3',
        JSON.stringify(fetched),
        expect.any(Number),
      );
    });
  });

  describe('invalidate()', () => {
    it('remove de L1 e chama redisService.delete', async () => {
      const fetcher = jest.fn().mockResolvedValue({ id: 'x' });
      await service.getOrFetch('key:x', fetcher, STRATEGY, OP);

      await service.invalidate('key:x');

      expect(redisDelete).toHaveBeenCalledWith('gw:cache:key:x');
    });
  });

  describe('pub/sub invalidação', () => {
    it('subscreve no canal cache:invalidate:instance no onModuleInit', () => {
      expect(pubSubSubscribe).toHaveBeenCalledWith(
        'cache:invalidate:instance',
        expect.any(Function),
      );
      expect(pubSubOn).toHaveBeenCalledWith('message', expect.any(Function));
    });
  });

  describe('contadores Prometheus', () => {
    it('registra gateway_cache_hits_total e gateway_cache_misses_total', () => {
      const names = registry.getMetricsAsArray().map((m) => m.name);
      expect(names).toContain('gateway_cache_hits_total');
      expect(names).toContain('gateway_cache_misses_total');
    });
  });
});
