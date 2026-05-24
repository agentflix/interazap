import { Test, TestingModule } from '@nestjs/testing';
import { HealthService } from './health.service';
import { RedisService } from '../infrastructure/redis/redis.service';
import { Logger } from '@nestjs/common';

describe('HealthService Coverage', () => {
  let service: HealthService;
  let redisService: RedisService;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: [
        HealthService,
        {
          provide: RedisService,
          useValue: {
            ping: jest.fn(),
            getStreamInfo: jest.fn(),
            getClient: jest.fn(() => ({
              xinfo: jest.fn(),
            })),
          },
        },
        {
          provide: Logger,
          useValue: {
            log: jest.fn(),
            error: jest.fn(),
          },
        },
      ],
    }).compile();

    service = module.get<HealthService>(HealthService);
    redisService = module.get<RedisService>(RedisService);
  });

  describe('checkConsumers', () => {
    it('should handle exceptions, log error, and return unhealthy status', async () => {
      jest.spyOn(redisService, 'getClient').mockImplementation(() => {
        throw new Error('Redis Client Error');
      });

      const result = await service.checkConsumers();

      expect(result).toEqual({
        status: 'unhealthy',
        message: 'Redis Client Error',
      });
    });

    it('should handle non-Error exceptions and return unhealthy status', async () => {
      jest.spyOn(redisService, 'getClient').mockImplementation(() => {
        // eslint-disable-next-line @typescript-eslint/only-throw-error
        throw 'String Error';
      });

      const result = await service.checkConsumers();

      expect(result).toEqual({
        status: 'unhealthy',
        message: 'Unknown error',
      });
    });
  });

  describe('determineOverallStatus', () => {
    it('should return unhealthy when ALL services are unhealthy', () => {
      const privateService = service as unknown as {
        determineOverallStatus: (
          services: Record<string, { status: string }>,
        ) => string;
      };
      const result = privateService.determineOverallStatus({
        db: { status: 'unhealthy' },
        redis: { status: 'unhealthy' },
      });

      expect(result).toBe('unhealthy');
    });

    it('should return degraded when SOME services are unhealthy', () => {
      const privateService = service as unknown as {
        determineOverallStatus: (
          services: Record<string, { status: string }>,
        ) => string;
      };
      const result = privateService.determineOverallStatus({
        db: { status: 'healthy' },
        redis: { status: 'unhealthy' },
      });

      expect(result).toBe('degraded');
    });

    it('should return healthy when ALL services are healthy', () => {
      const privateService = service as unknown as {
        determineOverallStatus: (
          services: Record<string, { status: string }>,
        ) => string;
      };
      const result = privateService.determineOverallStatus({
        db: { status: 'healthy' },
        redis: { status: 'healthy' },
      });

      expect(result).toBe('healthy');
    });
  });
});
