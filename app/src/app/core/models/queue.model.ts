/**
 * Métricas de uma fila BullMQ individual.
 *
 * Contexto: usado no painel de monitoramento de filas (admin/queues)
 * para exibir o estado atual de cada fila do gateway NestJS.
 */
export interface QueueMetrics {
  /** Nome identificador da fila. */
  name: string;
  /** Quantidade de jobs aguardando processamento. */
  waiting: number;
  /** Quantidade de jobs em processamento ativo. */
  active: number;
  /** Quantidade de jobs concluídos com sucesso. */
  completed: number;
  /** Quantidade de jobs com falha. */
  failed: number;
  /** Quantidade de jobs com execução adiada. */
  delayed: number;
  /** Indica se a fila está pausada. */
  paused: boolean;
  /** Latência média em milissegundos. */
  latency?: number;
  /** Taxa de processamento (jobs por segundo). */
  throughput?: number;
}

/**
 * Visão geral de todas as filas e do estado do Redis.
 *
 * Contexto: retornado pelo endpoint de monitoramento de filas,
 * agregando métricas de todas as filas BullMQ.
 */
export interface QueueOverview {
  queues: QueueMetrics[];
  /** Total de jobs em todas as filas. */
  totalJobs: number;
  totalFailed: number;
  totalCompleted: number;
  /** Tempo de uptime do servidor em segundos. */
  uptime: number;
  /** Estado da conexão e uso de memória do Redis. */
  redis: {
    connected: boolean;
    /** Memória utilizada pelo Redis (ex: "45.2 MB"). */
    memory: string;
  };
}

/**
 * Representa um job na fila de mensagens mortas (Dead Letter Queue).
 *
 * Contexto: jobs que excederam o número máximo de tentativas são movidos
 * para a DLQ. O painel admin permite inspecionar e reprocessar esses jobs.
 */
export interface DeadLetterJob {
  id: string;
  /** Nome do processador do job. */
  name: string;
  /** Nome da fila de origem. */
  queue: string;
  /** Dados do job. */
  data: Record<string, unknown>;
  /** Motivo da falha. */
  failedReason: string;
  /** Stack trace do erro que causou a falha. */
  stacktrace: string[];
  /** Número de tentativas realizadas. */
  attemptsMade: number;
  /** Número máximo de tentativas configurado. */
  maxAttempts: number;
  /** Data e hora da última falha. */
  failedAt: string;
  /** ID do tenant relacionado ao job, se aplicável. */
  tenant_id?: string;
  processedOn?: string;
  finishedOn?: string;
}

/**
 * Resposta paginada da API para listagem de jobs na DLQ.
 */
export interface DeadLetterQueueResponse {
  jobs: DeadLetterJob[];
  total: number;
  page: number;
  per_page: number;
  totalPages: number;
}

/**
 * Filtros para listagem de jobs na Dead Letter Queue.
 */
export interface DeadLetterFilters {
  queue?: string;
  jobName?: string;
  tenant_id?: string;
  from_date?: string;
  to_date?: string;
  page?: number;
  per_page?: number;
}

/**
 * Estado possível de um circuit breaker.
 *
 * - `CLOSED`: operando normalmente, requisições fluem
 * - `OPEN`: bloqueado após muitas falhas, requisições rejeitadas
 * - `HALF_OPEN`: testando se o serviço se recuperou
 */
export type CircuitState = 'CLOSED' | 'OPEN' | 'HALF_OPEN';

/**
 * Status detalhado de um circuit breaker individual.
 *
 * Contexto: usado no painel de monitoramento de circuit breakers
 * para visualizar o estado de cada integração externa protegida.
 */
export interface CircuitBreakerStatus {
  /** Nome identificador do circuit breaker. */
  name: string;
  state: CircuitState;
  /** Contagem de falhas consecutivas. */
  failures: number;
  /** Contagem de sucessos consecutivos. */
  successes: number;
  lastFailure?: string;
  lastSuccess?: string;
  /** Data e hora da próxima tentativa (quando OPEN). */
  nextRetryAt?: string;
  openedAt?: string;
  halfOpenAt?: string;
  /** Histórico de transições de estado. */
  history: CircuitStateChange[];
}

/**
 * Registro de uma transição de estado de um circuit breaker.
 */
export interface CircuitStateChange {
  from: CircuitState;
  to: CircuitState;
  timestamp: string;
  reason?: string;
}

/**
 * Visão geral de todos os circuit breakers do sistema.
 */
export interface CircuitBreakerOverview {
  circuits: CircuitBreakerStatus[];
  /** Quantidade de circuit breakers no estado OPEN. */
  totalOpen: number;
  /** Quantidade de circuit breakers no estado HALF_OPEN. */
  totalHalfOpen: number;
  /** Quantidade de circuit breakers no estado CLOSED (operando normalmente). */
  totalClosed: number;
}

/**
 * Resultado de uma ação executada em uma fila (pausar, retomar, limpar, etc.).
 */
export interface QueueActionResult {
  success: boolean;
  message: string;
  /** Número de jobs afetados pela ação. */
  affected?: number;
}
