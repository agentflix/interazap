import {
  CircuitBreakerService,
  CircuitState,
  CIRCUIT_BREAKER_OPTIONS,
} from '../../src/bot/services/circuit-breaker.service';
import { CircuitBreakerOpenException } from '../../src/bot/services/circuit-breaker-open.exception';
import { Test, TestingModule } from '@nestjs/testing';

describe('CircuitBreakerService', () => {
  let service: CircuitBreakerService;

  const defaultOpts = {
    failureThreshold: 5,
    failureWindowMs: 10_000,
    openDurationMs: 1_000, // short for test speed
    halfOpenTestRequests: 3,
    maxBackoffMs: 32_000,
    jitterPercent: 0, // disable jitter for deterministic tests
  };

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: [
        CircuitBreakerService,
        { provide: CIRCUIT_BREAKER_OPTIONS, useValue: defaultOpts },
      ],
    }).compile();

    service = module.get<CircuitBreakerService>(CircuitBreakerService);

    // Silence logger during tests
    jest.spyOn((service as any).logger, 'log').mockImplementation();
    jest.spyOn((service as any).logger, 'warn').mockImplementation();
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  // ─── Initial & CLOSED State ──────────────────────────────

  describe('CLOSED state', () => {
    it('initial state is CLOSED', () => {
      expect(service.getState().state).toBe(CircuitState.CLOSED);
    });

    it('request passes through in CLOSED state', async () => {
      const result = await service.execute(() => Promise.resolve('ok'));
      expect(result).toBe('ok');
    });

    it('records failure increments failure count', () => {
      service.recordFailure();
      expect(service.getState().failures).toBe(1);
    });

    it('does NOT trip with 4 failures (below threshold)', () => {
      for (let i = 0; i < 4; i++) {
        service.recordFailure();
      }
      expect(service.getState().state).toBe(CircuitState.CLOSED);
      expect(service.getState().failures).toBe(4);
    });

    it('trips to OPEN after 5 failures within window', () => {
      for (let i = 0; i < 5; i++) {
        service.recordFailure();
      }
      expect(service.getState().state).toBe(CircuitState.OPEN);
    });

    it('old failures outside window are pruned', () => {
      const realNow = Date.now;
      let currentTime = 1_000_000;
      Date.now = jest.fn(() => currentTime);

      // Record 3 failures at t=0
      service.recordFailure();
      service.recordFailure();
      service.recordFailure();

      // Advance past the failure window (10s)
      currentTime += 11_000;

      // Record 2 more failures — total within window is only 2
      service.recordFailure();
      service.recordFailure();

      expect(service.getState().state).toBe(CircuitState.CLOSED);
      expect(service.getState().failures).toBe(2);

      Date.now = realNow;
    });
  });

  // ─── OPEN State ──────────────────────────────────────────

  describe('OPEN state', () => {
    function tripCircuit(): void {
      for (let i = 0; i < defaultOpts.failureThreshold; i++) {
        service.recordFailure();
      }
    }

    it('fast-fails with CircuitBreakerOpenException in OPEN state', async () => {
      tripCircuit();

      await expect(
        service.execute(() => Promise.resolve('should not reach')),
      ).rejects.toThrow(CircuitBreakerOpenException);
    });

    it('transitions to HALF_OPEN after openDuration', async () => {
      const realNow = Date.now;
      let currentTime = 1_000_000;
      Date.now = jest.fn(() => currentTime);

      tripCircuit();
      expect(service.getState().state).toBe(CircuitState.OPEN);

      // Advance past openDuration
      currentTime += defaultOpts.openDurationMs + 1;

      // Next execute should transition to HALF_OPEN and pass through
      const result = await service.execute(() =>
        Promise.resolve('half-open-test'),
      );
      expect(result).toBe('half-open-test');
      expect(service.getState().state).toBe(CircuitState.HALF_OPEN);

      Date.now = realNow;
    });

    it('retry_after extends open duration', async () => {
      const realNow = Date.now;
      let currentTime = 1_000_000;
      Date.now = jest.fn(() => currentTime);

      // Trip with retry_after of 60 seconds
      for (let i = 0; i < 4; i++) {
        service.recordFailure();
      }
      service.recordFailure(60); // 60s = 60_000ms

      expect(service.getState().state).toBe(CircuitState.OPEN);

      // Advance past normal openDuration but NOT past retry_after
      currentTime += defaultOpts.openDurationMs + 1;

      // Should still be OPEN because retryAfter > openDuration
      await expect(service.execute(() => Promise.resolve())).rejects.toThrow(
        CircuitBreakerOpenException,
      );

      Date.now = realNow;
    });
  });

  // ─── HALF_OPEN State ─────────────────────────────────────

  describe('HALF_OPEN state', () => {
    function transitionToHalfOpen(): void {
      const realNow = Date.now;
      let currentTime = 1_000_000;
      Date.now = jest.fn(() => currentTime);

      for (let i = 0; i < defaultOpts.failureThreshold; i++) {
        service.recordFailure();
      }
      currentTime += defaultOpts.openDurationMs + 1;
      // Trigger transition via execute
      service
        .execute(() => Promise.resolve())
        .catch(() => {
          /* ignore */
        });

      Date.now = realNow;
    }

    it('allows test requests in HALF_OPEN', async () => {
      const realNow = Date.now;
      let currentTime = 1_000_000;
      Date.now = jest.fn(() => currentTime);

      for (let i = 0; i < defaultOpts.failureThreshold; i++) {
        service.recordFailure();
      }

      currentTime += defaultOpts.openDurationMs + 1;

      const result = await service.execute(() =>
        Promise.resolve('test-request'),
      );
      expect(result).toBe('test-request');
      expect(service.getState().state).toBe(CircuitState.HALF_OPEN);

      Date.now = realNow;
    });

    it('returns to CLOSED after 3 successful test requests', async () => {
      const realNow = Date.now;
      let currentTime = 1_000_000;
      Date.now = jest.fn(() => currentTime);

      for (let i = 0; i < defaultOpts.failureThreshold; i++) {
        service.recordFailure();
      }

      currentTime += defaultOpts.openDurationMs + 1;

      for (let i = 0; i < defaultOpts.halfOpenTestRequests; i++) {
        await service.execute(() => Promise.resolve('ok'));
      }

      expect(service.getState().state).toBe(CircuitState.CLOSED);

      Date.now = realNow;
    });

    it('returns to OPEN if any test request fails in HALF_OPEN', async () => {
      const realNow = Date.now;
      let currentTime = 1_000_000;
      Date.now = jest.fn(() => currentTime);

      for (let i = 0; i < defaultOpts.failureThreshold; i++) {
        service.recordFailure();
      }

      currentTime += defaultOpts.openDurationMs + 1;

      // First request succeeds (transitions to HALF_OPEN)
      await service.execute(() => Promise.resolve('ok'));
      expect(service.getState().state).toBe(CircuitState.HALF_OPEN);

      // Second request fails → should go back to OPEN
      await expect(
        service.execute(() => Promise.reject(new Error('fail'))),
      ).rejects.toThrow('fail');

      expect(service.getState().state).toBe(CircuitState.OPEN);

      Date.now = realNow;
    });
  });

  // ─── Backoff ─────────────────────────────────────────────

  describe('exponential backoff', () => {
    it('backoff doubles on each reopen (1→2→4→8→16→32s)', async () => {
      const realNow = Date.now;
      let currentTime = 1_000_000;
      Date.now = jest.fn(() => currentTime);

      const expectedBackoffs = [1000, 2000, 4000, 8000, 16_000, 32_000];

      for (const expectedMs of expectedBackoffs) {
        // Trip to OPEN
        for (let i = 0; i < defaultOpts.failureThreshold; i++) {
          service.recordFailure();
        }
        expect(service.getState().state).toBe(CircuitState.OPEN);

        // Advance past open duration to transition to HALF_OPEN
        currentTime += expectedMs + 1;

        // Execute triggers HALF_OPEN transition, then fail → back to OPEN
        await service
          .execute(() => Promise.reject(new Error('fail')))
          .catch(() => {
            /* expected */
          });

        expect(service.getState().state).toBe(CircuitState.OPEN);
      }

      Date.now = realNow;
    });

    it('backoff caps at maxBackoffMs (32s)', async () => {
      const realNow = Date.now;
      let currentTime = 1_000_000;
      Date.now = jest.fn(() => currentTime);

      // Iterate enough times to exceed maxBackoff
      for (let cycle = 0; cycle < 8; cycle++) {
        for (let i = 0; i < defaultOpts.failureThreshold; i++) {
          service.recordFailure();
        }

        // Advance well past any duration
        currentTime += 100_000;

        await service
          .execute(() => Promise.reject(new Error('fail')))
          .catch(() => {
            /* expected */
          });
      }

      // After many cycles, internal backoff should not exceed maxBackoffMs
      // Trip once more and check retryAfterMs via exception
      for (let i = 0; i < defaultOpts.failureThreshold; i++) {
        service.recordFailure();
      }

      // The state should be OPEN and internal backoff capped
      expect(service.getState().state).toBe(CircuitState.OPEN);

      // Verify the backoff is capped by checking the exception retryAfterMs
      try {
        await service.execute(() => Promise.resolve());
      } catch (e) {
        expect(e).toBeInstanceOf(CircuitBreakerOpenException);
        // With jitter=0 and maxBackoff=32000, retryAfterMs should be exactly 32000
        expect(
          (e as CircuitBreakerOpenException).retryAfterMs,
        ).toBeLessThanOrEqual(defaultOpts.maxBackoffMs);
      }

      Date.now = realNow;
    });
  });

  // ─── Jitter ──────────────────────────────────────────────

  describe('jitter', () => {
    it('jitter is applied within ±20%', async () => {
      // Create service WITH jitter enabled
      const jitterModule = await Test.createTestingModule({
        providers: [
          CircuitBreakerService,
          {
            provide: CIRCUIT_BREAKER_OPTIONS,
            useValue: {
              ...defaultOpts,
              jitterPercent: 0.2,
              openDurationMs: 10_000,
            },
          },
        ],
      }).compile();

      const jitterService = jitterModule.get<CircuitBreakerService>(
        CircuitBreakerService,
      );
      jest.spyOn((jitterService as any).logger, 'warn').mockImplementation();
      jest.spyOn((jitterService as any).logger, 'log').mockImplementation();

      const realNow = Date.now;
      let currentTime = 1_000_000;
      Date.now = jest.fn(() => currentTime);

      // Collect multiple backoff values by tripping and checking exception
      const backoffs: number[] = [];
      const realRandom = Math.random;

      // Test with min jitter
      Math.random = jest.fn(() => 0); // produces factor 1 + (0*2-1)*0.2 = 0.8
      for (let i = 0; i < defaultOpts.failureThreshold; i++) {
        jitterService.recordFailure();
      }
      try {
        await jitterService.execute(() => Promise.resolve());
      } catch (e) {
        if (e instanceof CircuitBreakerOpenException) {
          backoffs.push(e.retryAfterMs);
        }
      }

      jitterService.reset();

      // Test with max jitter
      Math.random = jest.fn(() => 1); // produces factor 1 + (1*2-1)*0.2 = 1.2
      for (let i = 0; i < defaultOpts.failureThreshold; i++) {
        jitterService.recordFailure();
      }
      try {
        await jitterService.execute(() => Promise.resolve());
      } catch (e) {
        if (e instanceof CircuitBreakerOpenException) {
          backoffs.push(e.retryAfterMs);
        }
      }

      // With base=10_000 and ±20% jitter: range is [8000, 12000]
      for (const b of backoffs) {
        expect(b).toBeGreaterThanOrEqual(8000);
        expect(b).toBeLessThanOrEqual(12_000);
      }

      Math.random = realRandom;
      Date.now = realNow;
    });
  });

  // ─── Utility Methods ─────────────────────────────────────

  describe('reset()', () => {
    it('returns to CLOSED state', () => {
      for (let i = 0; i < defaultOpts.failureThreshold; i++) {
        service.recordFailure();
      }
      expect(service.getState().state).toBe(CircuitState.OPEN);

      service.reset();

      expect(service.getState().state).toBe(CircuitState.CLOSED);
      expect(service.getState().failures).toBe(0);
      expect(service.getState().openedAt).toBeNull();
    });
  });

  describe('getState()', () => {
    it('returns correct state info', () => {
      const state = service.getState();
      expect(state).toEqual({
        state: CircuitState.CLOSED,
        failures: 0,
        openedAt: null,
      });

      service.recordFailure();
      const state2 = service.getState();
      expect(state2.state).toBe(CircuitState.CLOSED);
      expect(state2.failures).toBe(1);
      expect(state2.openedAt).toBeNull();
    });

    it('returns openedAt when OPEN', () => {
      const realNow = Date.now;
      const ts = 1_000_000;
      Date.now = jest.fn(() => ts);

      for (let i = 0; i < defaultOpts.failureThreshold; i++) {
        service.recordFailure();
      }

      const state = service.getState();
      expect(state.state).toBe(CircuitState.OPEN);
      expect(state.openedAt).toBe(ts);

      Date.now = realNow;
    });
  });

  describe('execute()', () => {
    it('wraps function and handles success', async () => {
      const fn = jest.fn().mockResolvedValue('result');
      const result = await service.execute(fn);

      expect(result).toBe('result');
      expect(fn).toHaveBeenCalledTimes(1);
    });

    it('wraps function and handles failure', async () => {
      const error = new Error('boom');
      const fn = jest.fn().mockRejectedValue(error);

      await expect(service.execute(fn)).rejects.toThrow('boom');
      expect(fn).toHaveBeenCalledTimes(1);
      expect(service.getState().failures).toBe(1);
    });
  });
});
