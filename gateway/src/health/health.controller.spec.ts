import { Test, TestingModule } from '@nestjs/testing';
import { HealthController } from './health.controller';
import { HealthService } from './health.service';

describe('HealthController', () => {
  let controller: HealthController;
  let service: HealthService;

  const mockHealthService = {
    checkAll: jest.fn(),
  };

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      controllers: [HealthController],
      providers: [
        {
          provide: HealthService,
          useValue: mockHealthService,
        },
      ],
    }).compile();

    controller = module.get<HealthController>(HealthController);
    service = module.get<HealthService>(HealthService);
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  describe('check()', () => {
    it('should return status ok', () => {
      const result = controller.check();
      expect(result).toEqual({ status: 'ok' });
    });
  });

  describe('deepCheck()', () => {
    it('should return healthy status when all services are ok', async () => {
      const mockHealth = {
        status: 'healthy',
        timestamp: new Date().toISOString(),
        services: {
          redis: { status: 'healthy', latency_ms: 5 },
          consumers: { status: 'healthy', latency_ms: 10 },
        },
      };

      mockHealthService.checkAll.mockResolvedValue(mockHealth);

      const result = await controller.deepCheck();

      expect(result.status).toBe('healthy');
      expect(result.services.redis.status).toBe('healthy');
      expect(result.services.consumers.status).toBe('healthy');
      expect(service.checkAll).toHaveBeenCalledTimes(1);
    });

    it('should return degraded status when redis is down', async () => {
      const mockHealth = {
        status: 'degraded',
        timestamp: new Date().toISOString(),
        services: {
          redis: { status: 'unhealthy', message: 'Connection refused' },
          consumers: { status: 'healthy', latency_ms: 10 },
        },
      };

      mockHealthService.checkAll.mockResolvedValue(mockHealth);

      const result = await controller.deepCheck();

      expect(result.status).toBe('degraded');
      expect(result.services.redis.status).toBe('unhealthy');
    });

    it('should return unhealthy status when all services are down', async () => {
      const mockHealth = {
        status: 'unhealthy',
        timestamp: new Date().toISOString(),
        services: {
          redis: { status: 'unhealthy', message: 'Connection refused' },
          consumers: { status: 'unhealthy', message: 'No active consumers' },
        },
      };

      mockHealthService.checkAll.mockResolvedValue(mockHealth);

      const result = await controller.deepCheck();

      expect(result.status).toBe('unhealthy');
    });
  });

  describe('ready()', () => {
    it('should return ready true when healthy', async () => {
      mockHealthService.checkAll.mockResolvedValue({ status: 'healthy' });

      const result = await controller.ready();

      expect(result).toEqual({ ready: true });
    });

    it('should return ready false when degraded', async () => {
      mockHealthService.checkAll.mockResolvedValue({ status: 'degraded' });

      const result = await controller.ready();

      expect(result).toEqual({ ready: false });
    });

    it('should return ready false when unhealthy', async () => {
      mockHealthService.checkAll.mockResolvedValue({ status: 'unhealthy' });

      const result = await controller.ready();

      expect(result).toEqual({ ready: false });
    });
  });

  describe('live()', () => {
    it('should return alive true', () => {
      const result = controller.live();
      expect(result).toEqual({ alive: true });
    });
  });
});
