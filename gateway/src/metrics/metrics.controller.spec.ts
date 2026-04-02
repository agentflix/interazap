import { Test, TestingModule } from '@nestjs/testing';
import { MetricsController } from './metrics.controller';
import { MetricsService } from './metrics.service';

describe('MetricsController', () => {
  let controller: MetricsController;
  let service: jest.Mocked<MetricsService>;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      controllers: [MetricsController],
      providers: [
        {
          provide: MetricsService,
          useValue: {
            getMetrics: jest.fn(),
          },
        },
      ],
    }).compile();

    controller = module.get<MetricsController>(MetricsController);
    service = module.get(MetricsService);
  });

  it('should be defined', () => {
    expect(controller).toBeDefined();
  });

  describe('metrics', () => {
    it('should return metrics from service', async () => {
      const mockMetrics = '# HELP test_metric\n# TYPE test_metric counter\n';
      service.getMetrics.mockResolvedValue(mockMetrics);

      const result = await controller.metrics();

      expect(result).toBe(mockMetrics);
      expect(service.getMetrics).toHaveBeenCalled();
    });
  });
});
