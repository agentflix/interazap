import { HealthController } from './health/health.controller';

describe('HealthController', () => {
  it('should expose ok status', () => {
    const controller = new HealthController();

    expect(controller.check()).toEqual({ status: 'ok' });
  });
});
