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

/**
 * Implementa o padrão Circuit Breaker para proteger chamadas à Telegram Bot API.
 *
 * Contexto: módulo bot. Gerencia os estados CLOSED, OPEN e HALF_OPEN com
 * backoff exponencial com jitter e suporte ao header retry_after do Telegram (HTTP 429).
 */
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
   * Executa uma função protegida pelo circuit breaker.
   * OPEN → lança CircuitBreakerOpenException (fast-fail).
   * HALF_OPEN → permite até halfOpenTestRequests tentativas de teste.
   * CLOSED → passa direto, contabiliza falhas em caso de erro.
   * @param fn Função assíncrona a ser protegida
   * @returns Resultado da função
   * @throws CircuitBreakerOpenException se o circuito estiver aberto
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
   * Registra uma falha da chamada à API do Telegram.
   * Aceita opcionalmente o valor retry_after de respostas HTTP 429 (em segundos).
   * @param retryAfter Tempo de espera sugerido pelo Telegram em segundos (opcional)
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
   * Registra um sucesso na execução. No estado HALF_OPEN, acumula sucessos
   * até atingir o threshold para retornar ao estado CLOSED.
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
   * Retorna o estado atual do circuit breaker para monitoramento.
   * @returns Estado atual, contagem de falhas na janela e timestamp de abertura
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
   * Força o reset para o estado CLOSED (para uso administrativo ou em testes).
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
   * Verifica se o número de falhas na janela deslizante excede o threshold configurado.
   * @returns true se o circuito deve ser aberto
   */
  private shouldTrip(): boolean {
    this.pruneOldFailures();
    return this.failures.length >= this.opts.failureThreshold;
  }

  /**
   * Transiciona para o estado OPEN com retry_after opcional do Telegram HTTP 429.
   * Na re-entrada a partir de HALF_OPEN, dobra o backoff (exponencial).
   * @param retryAfter Tempo sugerido pelo Telegram em segundos (opcional)
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
   * Transiciona para o estado HALF_OPEN, permitindo um número limitado de requisições de teste.
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
   * Retorna ao estado CLOSED após todas as requisições de teste passarem com sucesso.
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
   * Verifica se o período de espera do estado OPEN expirou.
   * @returns true se o tempo de espera foi superado
   */
  private isOpenExpired(): boolean {
    return Date.now() - this.openedAt >= this.retryAfterMs;
  }

  /**
   * Calcula o backoff com jitter: backoff * (1 + random(-jitter, +jitter)).
   * @returns Duração do backoff em milissegundos
   */
  private calculateBackoff(): number {
    const jitter = 1 + (Math.random() * 2 - 1) * this.opts.jitterPercent;
    return Math.round(this.currentBackoffMs * jitter);
  }

  /**
   * Remove timestamps de falhas fora da janela deslizante configurada.
   */
  private pruneOldFailures(): void {
    const cutoff = Date.now() - this.opts.failureWindowMs;
    this.failures = this.failures.filter((ts) => ts > cutoff);
  }
}
