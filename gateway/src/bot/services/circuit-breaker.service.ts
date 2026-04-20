import { Inject, Injectable, Logger, Optional } from '@nestjs/common';
import { CircuitBreakerOpenException } from './circuit-breaker-open.exception';

export enum CircuitState {
  CLOSED = 'CLOSED',
  OPEN = 'OPEN',
  HALF_OPEN = 'HALF_OPEN',
}

export interface CircuitBreakerOptions {
  failureThreshold: number;
  failureWindowMs: number;
  openDurationMs: number;
  halfOpenTestRequests: number;
  maxBackoffMs: number;
  jitterPercent: number;
}

export const CIRCUIT_BREAKER_OPTIONS = 'CIRCUIT_BREAKER_OPTIONS';

const DEFAULT_OPTIONS: CircuitBreakerOptions = {
  failureThreshold: 5,
  failureWindowMs: 10_000,
  openDurationMs: 30_000,
  halfOpenTestRequests: 3,
  maxBackoffMs: 32_000,
  jitterPercent: 0.2,
};

@Injectable()
export class CircuitBreakerService {
  private readonly logger = new Logger(CircuitBreakerService.name);
  private readonly opts: CircuitBreakerOptions;

  private state: CircuitState = CircuitState.CLOSED;
  private failures: number[] = [];
  private halfOpenSuccesses = 0;
  private halfOpenAttempts = 0;
  private openedAt = 0;
  private currentBackoffMs: number;
  private retryAfterMs = 0;

  constructor(
    @Inject(CIRCUIT_BREAKER_OPTIONS)
    @Optional()
    options?: Partial<CircuitBreakerOptions>,
  ) {
    this.opts = { ...DEFAULT_OPTIONS, ...options };
    this.currentBackoffMs = this.opts.openDurationMs;
  }

  /**
   * Execute a function through the circuit breaker.
   * OPEN → throw CircuitBreakerOpenException (fast-fail).
   * HALF_OPEN → allow up to halfOpenTestRequests.
   * CLOSED → pass through, count failures on error.
   */
  async execute<T>(fn: () => Promise<T>): Promise<T> {
    if (this.state === CircuitState.OPEN) {
      if (this.isOpenExpired()) {
        this.transitionToHalfOpen();
      } else {
        const remaining = this.retryAfterMs - (Date.now() - this.openedAt);
        throw new CircuitBreakerOpenException(
          this.state,
          Math.max(0, remaining),
        );
      }
    }

    if (
      this.state === CircuitState.HALF_OPEN &&
      this.halfOpenAttempts >= this.opts.halfOpenTestRequests
    ) {
      const remaining = this.retryAfterMs - (Date.now() - this.openedAt);
      throw new CircuitBreakerOpenException(this.state, Math.max(0, remaining));
    }

    try {
      const result = await fn();
      this.recordSuccess();
      return result;
    } catch (error) {
      this.recordFailure();
      throw error;
    }
  }

  /**
   * Record a failure from the Telegram API.
   * Optionally includes retry_after from 429 responses (in seconds).
   */
  recordFailure(retryAfter?: number): void {
    const now = Date.now();

    if (this.state === CircuitState.HALF_OPEN) {
      this.halfOpenAttempts++;
      this.logger.warn(
        `HALF_OPEN test request failed (attempt ${this.halfOpenAttempts}/${this.opts.halfOpenTestRequests})`,
      );
      this.transitionToOpen(retryAfter);
      return;
    }

    if (this.state === CircuitState.CLOSED) {
      this.failures.push(now);
      this.pruneOldFailures();

      this.logger.warn(
        `Failure recorded in CLOSED state (${this.failures.length}/${this.opts.failureThreshold} within window)`,
      );

      if (this.shouldTrip()) {
        this.transitionToOpen(retryAfter);
      }
    }
  }

  /**
   * Record a success.
   */
  recordSuccess(): void {
    if (this.state === CircuitState.HALF_OPEN) {
      this.halfOpenSuccesses++;
      this.halfOpenAttempts++;

      this.logger.log(
        `HALF_OPEN test request succeeded (${this.halfOpenSuccesses}/${this.opts.halfOpenTestRequests})`,
      );

      if (this.halfOpenSuccesses >= this.opts.halfOpenTestRequests) {
        this.transitionToClosed();
      }
      return;
    }

    if (this.state === CircuitState.CLOSED) {
      // No-op in CLOSED on success — just pass through
    }
  }

  /**
   * Get current state for monitoring.
   */
  getState(): {
    state: CircuitState;
    failures: number;
    openedAt: number | null;
  } {
    this.pruneOldFailures();
    return {
      state: this.state,
      failures: this.failures.length,
      openedAt: this.openedAt > 0 ? this.openedAt : null,
    };
  }

  /**
   * Force reset to CLOSED state (for admin/testing).
   */
  reset(): void {
    this.logger.log(`Circuit breaker force-reset from ${this.state} → CLOSED`);
    this.state = CircuitState.CLOSED;
    this.failures = [];
    this.halfOpenSuccesses = 0;
    this.halfOpenAttempts = 0;
    this.openedAt = 0;
    this.currentBackoffMs = this.opts.openDurationMs;
    this.retryAfterMs = 0;
  }

  // ─── Private Methods ──────────────────────────────────────

  /**
   * Check if failure count within window exceeds threshold.
   */
  private shouldTrip(): boolean {
    this.pruneOldFailures();
    return this.failures.length >= this.opts.failureThreshold;
  }

  /**
   * Transition to OPEN state with optional retry_after from Telegram 429.
   * On re-entry from HALF_OPEN, doubles the backoff (exponential).
   */
  private transitionToOpen(retryAfter?: number): void {
    const previousState = this.state;

    if (previousState === CircuitState.HALF_OPEN) {
      this.currentBackoffMs = Math.min(
        this.currentBackoffMs * 2,
        this.opts.maxBackoffMs,
      );
    } else {
      this.currentBackoffMs = this.opts.openDurationMs;
    }

    const telegramRetryMs = retryAfter ? retryAfter * 1000 : 0;
    const backoffWithJitter = this.calculateBackoff();
    this.retryAfterMs = Math.max(backoffWithJitter, telegramRetryMs);

    this.state = CircuitState.OPEN;
    this.openedAt = Date.now();
    this.halfOpenSuccesses = 0;
    this.halfOpenAttempts = 0;

    this.logger.warn(
      `Circuit breaker ${previousState} → OPEN (retryAfterMs=${this.retryAfterMs}, backoff=${this.currentBackoffMs}ms)`,
    );
  }

  /**
   * Transition to HALF_OPEN — allow limited test requests.
   */
  private transitionToHalfOpen(): void {
    this.state = CircuitState.HALF_OPEN;
    this.halfOpenSuccesses = 0;
    this.halfOpenAttempts = 0;

    this.logger.log(
      `Circuit breaker OPEN → HALF_OPEN (allowing ${this.opts.halfOpenTestRequests} test requests)`,
    );
  }

  /**
   * Transition back to CLOSED — all test requests passed.
   */
  private transitionToClosed(): void {
    this.logger.log(
      `Circuit breaker HALF_OPEN → CLOSED (all ${this.opts.halfOpenTestRequests} test requests passed)`,
    );

    this.state = CircuitState.CLOSED;
    this.failures = [];
    this.halfOpenSuccesses = 0;
    this.halfOpenAttempts = 0;
    this.openedAt = 0;
    this.currentBackoffMs = this.opts.openDurationMs;
    this.retryAfterMs = 0;
  }

  /**
   * Check if the OPEN wait period has expired.
   */
  private isOpenExpired(): boolean {
    return Date.now() - this.openedAt >= this.retryAfterMs;
  }

  /**
   * Calculate backoff with jitter: backoff * (1 + random(-jitter, +jitter)).
   */
  private calculateBackoff(): number {
    const jitter = 1 + (Math.random() * 2 - 1) * this.opts.jitterPercent;
    return Math.round(this.currentBackoffMs * jitter);
  }

  /**
   * Remove failure timestamps outside the sliding window.
   */
  private pruneOldFailures(): void {
    const cutoff = Date.now() - this.opts.failureWindowMs;
    this.failures = this.failures.filter((ts) => ts > cutoff);
  }
}
