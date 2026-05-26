import { CircuitState } from './circuit-breaker.service';

/**
 * Exceção lançada quando o circuit breaker está aberto (OPEN ou HALF_OPEN)
 * e rejeita a execução sem tentar a chamada remota.
 *
 * Contexto: módulo bot, proteção de chamadas à Telegram Bot API.
 */
export class CircuitBreakerOpenException extends Error {
  constructor(
    public readonly state: CircuitState,
    public readonly retryAfterMs: number,
  ) {
    super(`Circuit breaker is ${state}. Retry after ${retryAfterMs}ms`);
    this.name = 'CircuitBreakerOpenException';
  }
}
