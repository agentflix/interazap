import {
  CircuitBreakerService,
  CircuitState,
  CircuitOpenException,
} from './circuit-breaker.service';

describe('CircuitBreakerService', () => {
  let service: CircuitBreakerService;

  beforeEach(() => {
    service = new CircuitBreakerService();
  });

  describe('initial state', () => {
    it('should start with CLOSED state', async () => {
      await service.call('test', () => Promise.resolve('success'));
      expect(service.getState('test')).toBe(CircuitState.CLOSED);
    });

    it('should return undefined for unknown circuits', () => {
      expect(service.getState('unknown')).toBeUndefined();
    });
  });

  describe('successful calls', () => {
    it('should execute function and return result', async () => {
      const result = await service.call('test', () => Promise.resolve('hello'));
      expect(result).toBe('hello');
    });

    it('should remain CLOSED on success', async () => {
      await service.call('test', () => Promise.resolve('ok'));
      await service.call('test', () => Promise.resolve('ok'));
      await service.call('test', () => Promise.resolve('ok'));

      expect(service.getState('test')).toBe(CircuitState.CLOSED);
    });

    it('should reset failure count on success', async () => {
      // Cause some failures (but not enough to open)
      for (let i = 0; i < 3; i++) {
        try {
          await service.call('test', () => Promise.reject(new Error('fail')));
        } catch {
          // Expected failure
        }
      }

      // Success should reset counter
      await service.call('test', () => Promise.resolve('ok'));

      // Should still be closed
      expect(service.getState('test')).toBe(CircuitState.CLOSED);
    });
  });

  describe('failure handling', () => {
    it('should open circuit after threshold failures', async () => {
      const options = { failureThreshold: 3 };

      for (let i = 0; i < 3; i++) {
        try {
          await service.call(
            'test',
            () => Promise.reject(new Error('fail')),
            options,
          );
        } catch {
          // Expected failure
        }
      }

      expect(service.getState('test')).toBe(CircuitState.OPEN);
    });

    it('should throw CircuitOpenException when open', async () => {
      const options = { failureThreshold: 1 };

      try {
        await service.call(
          'test',
          () => Promise.reject(new Error('fail')),
          options,
        );
      } catch {
        // Expected failure
      }

      await expect(
        service.call('test', () => Promise.resolve('ok'), options),
      ).rejects.toThrow(CircuitOpenException);
    });

    it('should call fallback when circuit is open', async () => {
      const options = { failureThreshold: 1 };

      try {
        await service.call(
          'test',
          () => Promise.reject(new Error('fail')),
          options,
        );
      } catch {
        // Expected failure
      }

      const result = await service.call(
        'test',
        () => Promise.resolve('main'),
        options,
        () => Promise.resolve('fallback'),
      );

      expect(result).toBe('fallback');
    });
  });

  describe('half-open state', () => {
    it('should transition to HALF_OPEN after reset timeout', async () => {
      const options = {
        failureThreshold: 1,
        resetTimeout: 10,
        successThreshold: 1,
      };

      try {
        await service.call(
          'test',
          () => Promise.reject(new Error('fail')),
          options,
        );
      } catch {
        // Expected failure
      }

      expect(service.getState('test')).toBe(CircuitState.OPEN);

      // Wait for reset timeout
      await new Promise((r) => setTimeout(r, 20));

      // Next call should transition to half-open and then close on success
      await service.call('test', () => Promise.resolve('ok'), options);

      // Should be CLOSED after success in half-open (with successThreshold: 1)
      expect(service.getState('test')).toBe(CircuitState.CLOSED);
    });

    it('should re-open on failure in HALF_OPEN', async () => {
      const options = { failureThreshold: 1, resetTimeout: 10 };

      try {
        await service.call(
          'test',
          () => Promise.reject(new Error('fail')),
          options,
        );
      } catch {
        // Expected failure
      }

      await new Promise((r) => setTimeout(r, 20));

      try {
        await service.call(
          'test',
          () => Promise.reject(new Error('fail again')),
          options,
        );
      } catch {
        // Expected failure
      }

      expect(service.getState('test')).toBe(CircuitState.OPEN);
    });

    it('should require successThreshold successes to close', async () => {
      const options = {
        failureThreshold: 1,
        resetTimeout: 10,
        successThreshold: 3,
      };

      try {
        await service.call(
          'test',
          () => Promise.reject(new Error('fail')),
          options,
        );
      } catch {
        // Expected failure
      }

      await new Promise((r) => setTimeout(r, 20));

      // First success - still half-open
      await service.call('test', () => Promise.resolve('ok'), options);
      expect(service.getState('test')).toBe(CircuitState.HALF_OPEN);

      // Second success - still half-open
      await service.call('test', () => Promise.resolve('ok'), options);
      expect(service.getState('test')).toBe(CircuitState.HALF_OPEN);

      // Third success - should close
      await service.call('test', () => Promise.resolve('ok'), options);
      expect(service.getState('test')).toBe(CircuitState.CLOSED);
    });
  });

  describe('manual controls', () => {
    it('should reset circuit to CLOSED', async () => {
      const options = { failureThreshold: 1 };

      try {
        await service.call(
          'test',
          () => Promise.reject(new Error('fail')),
          options,
        );
      } catch {
        // Expected failure
      }

      expect(service.getState('test')).toBe(CircuitState.OPEN);

      const success = service.reset('test');

      expect(success).toBe(true);
      expect(service.getState('test')).toBe(CircuitState.CLOSED);
    });

    it('should force circuit to OPEN', async () => {
      await service.call('test', () => Promise.resolve('ok'));
      expect(service.getState('test')).toBe(CircuitState.CLOSED);

      const success = service.forceOpen('test');

      expect(success).toBe(true);
      expect(service.getState('test')).toBe(CircuitState.OPEN);
    });

    it('should return false for unknown circuits', () => {
      expect(service.reset('unknown')).toBe(false);
      expect(service.forceOpen('unknown')).toBe(false);
    });
  });

  describe('getAllCircuits', () => {
    it('should return all circuits with status', async () => {
      await service.call('service-a', () => Promise.resolve('ok'));
      await service.call('service-b', () => Promise.resolve('ok'));

      const circuits = service.getAllCircuits();

      expect(Object.keys(circuits)).toHaveLength(2);
      expect(circuits['service-a'].state).toBe(CircuitState.CLOSED);
      expect(circuits['service-b'].state).toBe(CircuitState.CLOSED);
    });
  });

  describe('custom options', () => {
    it('should use custom failure threshold', async () => {
      const options = { failureThreshold: 10 };

      for (let i = 0; i < 9; i++) {
        try {
          await service.call(
            'test',
            () => Promise.reject(new Error('fail')),
            options,
          );
        } catch {
          // Expected failure
        }
      }

      // Should still be closed (threshold is 10)
      expect(service.getState('test')).toBe(CircuitState.CLOSED);

      try {
        await service.call(
          'test',
          () => Promise.reject(new Error('fail')),
          options,
        );
      } catch {
        // Expected failure
      }

      // Now should be open
      expect(service.getState('test')).toBe(CircuitState.OPEN);
    });
  });
});
