import { CircuitState } from './circuit-breaker.service';

export class CircuitBreakerOpenException extends Error {
  constructor(
    public readonly state: CircuitState,
    public readonly retryAfterMs: number,
  ) {
    super(`Circuit breaker is ${state}. Retry after ${retryAfterMs}ms`);
    this.name = 'CircuitBreakerOpenException';
  }
}
