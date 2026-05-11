import { Test, TestingModule } from '@nestjs/testing';
import { HealthController } from './health/health.controller';
import { HealthService } from './health/health.service';

describe('HealthController', () => {
  it('should expose ok status', async () => {
    const module: TestingModule = await Test.createTestingModule({
      controllers: [HealthController],
      providers: [
        {
          provide: HealthService,
          useValue: { checkAll: jest.fn() },
        },
      ],
    }).compile();

    const controller = module.get<HealthController>(HealthController);

    expect(controller.check()).toEqual({ status: 'ok' });
  });
});
