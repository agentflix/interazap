/**
 * Modelos do Circuit Breaker do gateway.
 * Define os tipos, enums e interfaces utilizados pelo serviço de circuit breaker.
 */

/**
 * Estado possível de um circuit breaker.
 * - CLOSED: operação normal, requisições passam normalmente.
 * - OPEN: circuito aberto, requisições falham imediatamente.
 * - HALF_OPEN: testando se o serviço se recuperou.
 */
export enum CircuitState {
  CLOSED = 'CLOSED',
  OPEN = 'OPEN',
  HALF_OPEN = 'HALF_OPEN',
}

/**
 * Listener invocado sempre que um circuito muda de estado.
 *
 * @param serviceName - Nome do serviço cujo circuito mudou
 * @param previousState - Estado anterior do circuito
 * @param nextState - Novo estado do circuito
 */
export type CircuitStateChangeHandler = (
  serviceName: string,
  previousState: CircuitState,
  nextState: CircuitState,
) => void;

/**
 * Opções de configuração de um circuit breaker.
 */
export interface CircuitOptions {
  /** Número de falhas antes de abrir o circuito */
  failureThreshold?: number;
  /** Tempo em ms antes de tentar fechar o circuito */
  resetTimeout?: number;
  /** Número de chamadas bem-sucedidas em half-open para fechar o circuito */
  successThreshold?: number;
  /** Nome opcional para logging */
  name?: string;
  /** Listener opcional para transições de estado */
  onStateChange?: CircuitStateChangeHandler;
}

/**
 * Rastreamento interno do estado de um circuito.
 */
export interface CircuitInfo {
  /** Estado atual do circuito */
  state: CircuitState;
  /** Contagem de falhas consecutivas */
  failures: number;
  /** Contagem de sucessos consecutivos (em half-open) */
  successes: number;
  /** Timestamp da última falha */
  lastFailure: number;
  /** Timestamp da última mudança de estado */
  lastStateChange: number;
  /** Opções resolvidas com defaults aplicados */
  options: Required<CircuitOptions>;
}
