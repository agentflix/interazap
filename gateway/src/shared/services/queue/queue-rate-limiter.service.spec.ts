import { Test, TestingModule } from '@nestjs/testing';
import { QueueRateLimiterService } from './queue-rate-limiter.service';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { QueueRateLimiter } from './bullmq-resilience.config';

describe('QueueRateLimiterService', () => {
  let service: QueueRateLimiterService;
  let redisService: jest.Mocked<RedisService>;
  let mockRedisClient: {
    zremrangebyscore: jest.Mock;
    zcard: jest.Mock;
    zadd: jest.Mock;
    expire: jest.Mock;
    del: jest.Mock;
    multi: jest.Mock;
  };

  const defaultConfig: QueueRateLimiter = {
    max: 10,
    duration: 60000,
  };

  beforeEach(async () => {
    mockRedisClient = {
      zremrangebyscore: jest.fn().mockResolvedValue(0),
      zcard: jest.fn().mockResolvedValue(5),
      zadd: jest.fn().mockResolvedValue(1),
      expire: jest.fn().mockResolvedValue(1),
      del: jest.fn().mockResolvedValue(1),
      multi: jest.fn().mockReturnValue({
        zremrangebyscore: jest.fn().mockReturnThis(),
        zcard: jest.fn().mockReturnThis(),
        exec: jest.fn().mockResolvedValue([
          [null, 0],
          [null, 5],
        ]),
      }),
    };

    redisService = {
      getClient: jest.fn().mockReturnValue(mockRedisClient),
    } as unknown as jest.Mocked<RedisService>;

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        QueueRateLimiterService,
        { provide: RedisService, useValue: redisService },
      ],
    }).compile();

    service = module.get<QueueRateLimiterService>(QueueRateLimiterService);
  });

  describe('check', () => {
    it('should return allowed when under limit', async () => {
      mockRedisClient.zcard.mockResolvedValue(5);

      const result = await service.check('test-queue', defaultConfig);

      expect(result.allowed).toBe(true);
      expect(result.current).toBe(5);
      expect(result.limit).toBe(10);
      expect(result.remaining).toBe(5);
    });

    it('should return not allowed when at limit', async () => {
      mockRedisClient.zcard.mockResolvedValue(10);

      const result = await service.check('test-queue', defaultConfig);

      expect(result.allowed).toBe(false);
      expect(result.current).toBe(10);
      expect(result.remaining).toBe(0);
    });

    it('should return not allowed when over limit', async () => {
      mockRedisClient.zcard.mockResolvedValue(15);

      const result = await service.check('test-queue', defaultConfig);

      expect(result.allowed).toBe(false);
      expect(result.remaining).toBe(0);
    });

    it('should fail open on Redis error', async () => {
      mockRedisClient.zremrangebyscore.mockRejectedValue(
        new Error('Redis error'),
      );

      const result = await service.check('test-queue', defaultConfig);

      expect(result.allowed).toBe(true);
      expect(result.current).toBe(0);
    });

    it('should calculate correct reset time', async () => {
      const result = await service.check('test-queue', defaultConfig);

      expect(result.resetInSeconds).toBe(60);
    });
  });

  describe('consume', () => {
    it('should consume a rate limit slot', async () => {
      const multi = {
        zremrangebyscore: jest.fn().mockReturnThis(),
        zcard: jest.fn().mockReturnThis(),
        exec: jest.fn().mockResolvedValue([
          [null, 0],
          [null, 5],
        ]),
      };
      mockRedisClient.multi.mockReturnValue(multi);

      const result = await service.consume('test-queue', defaultConfig);

      expect(result.allowed).toBe(true);
      expect(result.current).toBe(6);
      expect(mockRedisClient.zadd).toHaveBeenCalled();
      expect(mockRedisClient.expire).toHaveBeenCalled();
    });

    it('should not consume when at limit', async () => {
      const multi = {
        zremrangebyscore: jest.fn().mockReturnThis(),
        zcard: jest.fn().mockReturnThis(),
        exec: jest.fn().mockResolvedValue([
          [null, 0],
          [null, 10],
        ]),
      };
      mockRedisClient.multi.mockReturnValue(multi);

      const result = await service.consume('test-queue', defaultConfig);

      expect(result.allowed).toBe(false);
      expect(mockRedisClient.zadd).not.toHaveBeenCalled();
    });

    it('should use custom identifier', async () => {
      const multi = {
        zremrangebyscore: jest.fn().mockReturnThis(),
        zcard: jest.fn().mockReturnThis(),
        exec: jest.fn().mockResolvedValue([
          [null, 0],
          [null, 0],
        ]),
      };
      mockRedisClient.multi.mockReturnValue(multi);

      await service.consume('test-queue', defaultConfig, 'custom-id');

      expect(mockRedisClient.zadd).toHaveBeenCalledWith(
        'queue:ratelimit:test-queue',
        expect.any(Number),
        'custom-id',
      );
    });

    it('should fail open on Redis error', async () => {
      const multi = {
        zremrangebyscore: jest.fn().mockReturnThis(),
        zcard: jest.fn().mockReturnThis(),
        exec: jest.fn().mockRejectedValue(new Error('Redis error')),
      };
      mockRedisClient.multi.mockReturnValue(multi);

      const result = await service.consume('test-queue', defaultConfig);

      expect(result.allowed).toBe(true);
    });
  });

  describe('reset', () => {
    it('should reset rate limit', async () => {
      const result = await service.reset('test-queue');

      expect(result).toBe(true);
      expect(mockRedisClient.del).toHaveBeenCalledWith(
        'queue:ratelimit:test-queue',
      );
    });

    it('should return false on error', async () => {
      mockRedisClient.del.mockRejectedValue(new Error('Redis error'));

      const result = await service.reset('test-queue');

      expect(result).toBe(false);
    });
  });

  describe('getStats', () => {
    it('should return rate limiter stats', async () => {
      mockRedisClient.zcard.mockResolvedValue(7);

      const stats = await service.getStats('test-queue', defaultConfig);

      expect(stats.queueName).toBe('test-queue');
      expect(stats.current).toBe(7);
      expect(stats.limit).toBe(10);
      expect(stats.windowSeconds).toBe(60);
      expect(stats.utilizationPercent).toBe(70);
    });

    it('should handle zero limit', async () => {
      const stats = await service.getStats('test-queue', {
        max: 0,
        duration: 60000,
      });

      expect(stats.utilizationPercent).toBe(0);
    });
  });

  describe('getAllStats', () => {
    it('should return stats for multiple queues', async () => {
      const configs: Record<string, QueueRateLimiter> = {
        'queue-1': { max: 10, duration: 60000 },
        'queue-2': { max: 20, duration: 30000 },
      };

      const stats = await service.getAllStats(configs);

      expect(stats.length).toBe(2);
      expect(stats[0].queueName).toBe('queue-1');
      expect(stats[1].queueName).toBe('queue-2');
    });
  });

  describe('isRateLimited', () => {
    it('should return true when limited', async () => {
      mockRedisClient.zcard.mockResolvedValue(10);

      const result = await service.isRateLimited('test-queue', defaultConfig);

      expect(result).toBe(true);
    });

    it('should return false when not limited', async () => {
      mockRedisClient.zcard.mockResolvedValue(5);

      const result = await service.isRateLimited('test-queue', defaultConfig);

      expect(result).toBe(false);
    });
  });

  describe('waitForSlot', () => {
    it('should return immediately if slot available', async () => {
      const multi = {
        zremrangebyscore: jest.fn().mockReturnThis(),
        zcard: jest.fn().mockReturnThis(),
        exec: jest.fn().mockResolvedValue([
          [null, 0],
          [null, 5],
        ]),
      };
      mockRedisClient.multi.mockReturnValue(multi);

      const result = await service.waitForSlot(
        'test-queue',
        defaultConfig,
        1000,
      );

      expect(result.allowed).toBe(true);
    });

    it('should throw on timeout', async () => {
      const multi = {
        zremrangebyscore: jest.fn().mockReturnThis(),
        zcard: jest.fn().mockReturnThis(),
        exec: jest.fn().mockResolvedValue([
          [null, 0],
          [null, 100], // Always over limit
        ]),
      };
      mockRedisClient.multi.mockReturnValue(multi);

      await expect(
        service.waitForSlot('test-queue', defaultConfig, 100),
      ).rejects.toThrow('Rate limit timeout exceeded');
    });
  });
});
