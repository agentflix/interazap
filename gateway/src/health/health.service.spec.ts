import { Test, TestingModule } from '@nestjs/testing';
import { HealthService } from './health.service';
import { RedisService } from '../infrastructure/redis/redis.service';

describe('HealthService', () => {
  let service: HealthService;

  const mockRedisClient = {
    ping: jest.fn(),
    xinfo: jest.fn(),
  };

  const mockRedisService = {
    getClient: jest.fn(() => mockRedisClient),
  };

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: [
        HealthService,
        {
          provide: RedisService,
          useValue: mockRedisService,
        },
      ],
    }).compile();

    service = module.get<HealthService>(HealthService);
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  describe('checkAll()', () => {
    it('should return healthy status when all services are ok', async () => {
      mockRedisClient.ping.mockResolvedValue('PONG');
      mockRedisClient.xinfo.mockResolvedValue([]);

      const result = await service.checkAll();

      expect(result.status).toBe('healthy');
      expect(result.timestamp).toBeDefined();
      expect(result.services.redis.status).toBe('healthy');
      expect(result.services.consumers.status).toBe('healthy');
    });

    it('should return degraded status when redis is down', async () => {
      mockRedisClient.ping.mockRejectedValue(new Error('Connection refused'));
      mockRedisClient.xinfo.mockResolvedValue([]);

      const result = await service.checkAll();

      expect(result.status).toBe('degraded');
      expect(result.services.redis.status).toBe('unhealthy');
      expect(result.services.redis.message).toBe('Connection refused');
    });

    it('should include latency in healthy responses', async () => {
      mockRedisClient.ping.mockResolvedValue('PONG');
      mockRedisClient.xinfo.mockResolvedValue([]);

      const result = await service.checkAll();

      expect(result.services.redis.latency_ms).toBeDefined();
      expect(typeof result.services.redis.latency_ms).toBe('number');
    });
  });

  describe('checkRedis()', () => {
    it('should return healthy when ping succeeds', async () => {
      mockRedisClient.ping.mockResolvedValue('PONG');

      const result = await service.checkRedis();

      expect(result.status).toBe('healthy');
      expect(result.latency_ms).toBeDefined();
    });

    it('should return unhealthy when ping fails', async () => {
      mockRedisClient.ping.mockRejectedValue(new Error('ECONNREFUSED'));

      const result = await service.checkRedis();

      expect(result.status).toBe('unhealthy');
      expect(result.message).toBe('ECONNREFUSED');
    });

    it('should return unhealthy when ping returns wrong value', async () => {
      mockRedisClient.ping.mockResolvedValue('WRONG');

      const result = await service.checkRedis();

      expect(result.status).toBe('unhealthy');
      expect(result.message).toContain('ping failed');
    });
  });

  describe('checkConsumers()', () => {
    it('should return healthy with stream details', async () => {
      mockRedisClient.xinfo.mockResolvedValue(['stream info']);

      const result = await service.checkConsumers();

      expect(result.status).toBe('healthy');
      expect(result.details).toBeDefined();
      expect(result.details?.monitored_streams).toBeDefined();
    });

    it('should handle missing streams gracefully', async () => {
      mockRedisClient.xinfo.mockRejectedValue(new Error('ERR no such key'));

      const result = await service.checkConsumers();

      // Should still be healthy as streams not existing yet is OK
      expect(result.status).toBe('healthy');
      expect(result.details?.stream_errors).toEqual([]);
    });

    it('should return unhealthy when any stream has an unexpected inspection error', async () => {
      mockRedisClient.xinfo.mockImplementation((_command, stream) => {
        if (stream === 'billing.payment_received') {
          throw new Error(
            'READONLY You cannot write against a read only replica.',
          );
        }

        return ['stream info'];
      });

      const result = await service.checkConsumers();

      expect(result.status).toBe('unhealthy');
      expect(result.message).toBe('One or more streams could not be inspected');
      expect(result.details?.stream_errors).toEqual([
        {
          stream: 'billing.payment_received',
          error: 'READONLY You cannot write against a read only replica.',
        },
      ]);
    });

    it('should return degraded overall status when consumers have an unexpected stream error', async () => {
      mockRedisClient.ping.mockResolvedValue('PONG');
      mockRedisClient.xinfo.mockImplementation((_command, stream) => {
        if (stream === 'ai.chat_request') {
          throw new Error('Unexpected stream failure');
        }

        return ['stream info'];
      });

      const result = await service.checkAll();

      expect(result.status).toBe('degraded');
      expect(result.services.redis.status).toBe('healthy');
      expect(result.services.consumers.status).toBe('unhealthy');
    });
  });

  describe('checkRedis edge cases', () => {
    it('should handle non-Error exceptions', async () => {
      mockRedisClient.ping.mockRejectedValue('string error');

      const result = await service.checkRedis();

      expect(result.status).toBe('unhealthy');
      expect(result.message).toBe('Unknown error');
    });
  });
});
