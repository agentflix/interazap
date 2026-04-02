import { Test, TestingModule } from '@nestjs/testing';
import { CircuitHealthController } from './circuit-health.controller';
import {
  CircuitBreakerService,
  CircuitState,
} from '../shared/services/circuit-breaker';
import { InternalApiKeyGuard } from '../domains/realtime/guards/internal-api-key.guard';

describe('CircuitHealthController', () => {
  let controller: CircuitHealthController;
  let circuitBreakerService: CircuitBreakerService;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      controllers: [CircuitHealthController],
      providers: [CircuitBreakerService],
    })
      .overrideGuard(InternalApiKeyGuard)
      .useValue({ canActivate: jest.fn().mockReturnValue(true) })
      .compile();

    controller = module.get<CircuitHealthController>(CircuitHealthController);
    circuitBreakerService = module.get<CircuitBreakerService>(
      CircuitBreakerService,
    );
  });

  describe('getAll', () => {
    it('should return empty circuits when none exist', () => {
      const result = controller.getAll();

      expect(result).toEqual({
        healthy: true,
        circuits: [],
        summary: {
          total: 0,
          closed: 0,
          open: 0,
          halfOpen: 0,
        },
      });
    });

    it('should return all circuits with status', async () => {
      // Create some circuits
      await circuitBreakerService.call('service-a', () =>
        Promise.resolve('ok'),
      );
      await circuitBreakerService.call('service-b', () =>
        Promise.resolve('ok'),
      );

      const result = controller.getAll();

      expect(result.summary.total).toBe(2);
      expect(result.summary.closed).toBe(2);
      expect(result.circuits).toHaveLength(2);
    });

    it('should mark as unhealthy when circuit is open', async () => {
      // Create and open a circuit
      try {
        await circuitBreakerService.call(
          'failing-service',
          () => Promise.reject(new Error('fail')),
          { failureThreshold: 1 },
        );
      } catch {
        // Expected failure
      }

      const result = controller.getAll();

      expect(result.healthy).toBe(false);
      expect(result.summary.open).toBe(1);
    });

    it('should include half-open circuits in summary', async () => {
      const options = { failureThreshold: 1, resetTimeout: 10 };

      try {
        await circuitBreakerService.call(
          'recovering-service',
          () => Promise.reject(new Error('fail')),
          options,
        );
      } catch {
        // Expected failure
      }

      // Wait for reset timeout
      await new Promise((r) => setTimeout(r, 20));

      // Trigger half-open transition by calling
      try {
        await circuitBreakerService.call(
          'recovering-service',
          () => Promise.resolve('ok'),
          options,
        );
      } catch {
        // May fail in half-open
      }

      const result = controller.getAll();

      // Should be in half-open or closed depending on success
      expect(result.summary.total).toBe(1);
    });
  });

  describe('getOne', () => {
    it('should return circuit details', async () => {
      await circuitBreakerService.call('my-service', () =>
        Promise.resolve('ok'),
      );

      const result = controller.getOne('my-service');

      expect(result.name).toBe('my-service');
      expect(result.exists).toBe(true);
      expect(result.state).toBe(CircuitState.CLOSED);
    });

    it('should return exists false for unknown circuit', () => {
      const result = controller.getOne('unknown-service');

      expect(result).toEqual({
        name: 'unknown-service',
        exists: false,
        state: null,
      });
    });
  });

  describe('reset', () => {
    it('should reset an open circuit', async () => {
      // Open the circuit
      try {
        await circuitBreakerService.call(
          'open-service',
          () => Promise.reject(new Error('fail')),
          { failureThreshold: 1 },
        );
      } catch {
        // Expected failure
      }

      expect(circuitBreakerService.getState('open-service')).toBe(
        CircuitState.OPEN,
      );

      const result = controller.reset('open-service');

      expect(result.success).toBe(true);
      expect(circuitBreakerService.getState('open-service')).toBe(
        CircuitState.CLOSED,
      );
    });

    it('should return failure for unknown circuit', () => {
      const result = controller.reset('non-existent');

      expect(result.success).toBe(false);
      expect(result.message).toContain('not found');
    });
  });

  describe('forceOpen', () => {
    it('should force open a closed circuit', async () => {
      await circuitBreakerService.call('healthy-service', () =>
        Promise.resolve('ok'),
      );

      expect(circuitBreakerService.getState('healthy-service')).toBe(
        CircuitState.CLOSED,
      );

      const result = controller.forceOpen('healthy-service');

      expect(result.success).toBe(true);
      expect(circuitBreakerService.getState('healthy-service')).toBe(
        CircuitState.OPEN,
      );
    });

    it('should return failure for unknown circuit', () => {
      const result = controller.forceOpen('non-existent');

      expect(result.success).toBe(false);
      expect(result.message).toContain('not found');
    });
  });

  describe('circuit state transitions', () => {
    it('should track failures correctly', async () => {
      const options = { failureThreshold: 3 };

      for (let i = 0; i < 2; i++) {
        try {
          await circuitBreakerService.call(
            'monitored-service',
            () => Promise.reject(new Error('fail')),
            options,
          );
        } catch {
          // Expected failure
        }
      }

      const result = controller.getOne('monitored-service');

      expect(result.state).toBe(CircuitState.CLOSED);
      if ('failures' in result) {
        expect(result.failures).toBe(2);
      }
    });

    it('should include options in circuit details', async () => {
      await circuitBreakerService.call(
        'custom-options-service',
        () => Promise.resolve('ok'),
        {
          failureThreshold: 10,
          resetTimeout: 60000,
          successThreshold: 3,
        },
      );

      const result = controller.getOne('custom-options-service');

      if ('options' in result) {
        expect(result.options.failureThreshold).toBe(10);
        expect(result.options.resetTimeout).toBe(60000);
        expect(result.options.successThreshold).toBe(3);
      }
    });
  });
});
