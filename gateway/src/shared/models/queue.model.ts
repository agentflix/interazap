/**
 * Modelos de infraestrutura de filas do gateway.
 * Define interfaces e tipos para configuração de resiliência, DLQ, rate limiting
 * e estatísticas utilizados pelos serviços de fila (Redis Streams e BullMQ).
 */

// ─── Redis Streams Queue ─────────────────────────────────────────────────────

/**
 * Configuração de política de retry com backoff exponencial.
 */
export interface RetryConfig {
  /** Número máximo de tentativas */
  maxAttempts: number;
  /** Delay base em ms para backoff exponencial */
  baseDelayMs: number;
  /** Multiplicador do backoff exponencial */
  exponentialBase: number;
  /** Delay máximo em ms */
  maxDelayMs: number;
}

/**
 * Configuração de rate limiting para filas.
 */
export interface RateLimitConfig {
  /** Máximo de operações por janela */
  maxOperations: number;
  /** Tamanho da janela em segundos */
  windowSeconds: number;
}

/**
 * Configuração do Dead Letter Queue (DLQ).
 */
export interface DlqConfig {
  /** Se o DLQ está habilitado */
  enabled: boolean;
  /** Sufixo do stream para o DLQ */
  suffix: string;
  /** Idade máxima em segundos antes da limpeza */
  maxAgeSeconds: number;
}

/**
 * Configuração completa de uma fila Redis Stream.
 */
export interface QueueConfig {
  /** Nome do stream */
  stream: string;
  /** Nome do consumer group */
  group: string;
  /** Configuração de retry */
  retry: RetryConfig;
  /** Configuração de rate limiting */
  rateLimit?: RateLimitConfig;
  /** Configuração do DLQ */
  dlq: DlqConfig;
  /** Timeout de processamento em ms */
  timeoutMs: number;
}

/**
 * Entrada no Dead Letter Queue do Redis Stream.
 */
export interface DlqEntry {
  /** Nome do stream original */
  originalStream: string;
  /** ID da mensagem original */
  originalMessageId: string;
  /** Payload da mensagem */
  payload: Record<string, unknown>;
  /** Mensagem de erro */
  error: string;
  /** Número de tentativas realizadas */
  attempts: number;
  /** Timestamp da falha */
  failedAt: string;
  /** Contador de retentativas via DLQ */
  retryCount: number;
}

// ─── BullMQ ──────────────────────────────────────────────────────────────────

/**
 * Configuração de rate limiter para uma fila BullMQ.
 */
export interface QueueRateLimiter {
  /** Número máximo de jobs a processar na janela */
  max: number;
  /** Duração da janela em milissegundos */
  duration: number;
}

/**
 * Configuração específica de uma fila BullMQ.
 */
export interface BullMQQueueConfig {
  /** Nome da fila */
  name: string;
  /** Opções padrão de job para esta fila */
  defaultJobOptions: import('bullmq').DefaultJobOptions;
  /** Configuração de rate limiter */
  rateLimiter?: QueueRateLimiter;
  /** Concorrência do worker */
  concurrency: number;
  /** Timeout de processamento em ms */
  timeoutMs: number;
}

/**
 * Entrada no DLQ do BullMQ com informações do job falho.
 */
export interface BullMQDlqEntry {
  /** ID do job original */
  originalJobId: string;
  /** Nome da fila original */
  originalQueue: string;
  /** Dados/payload do job */
  data: Record<string, unknown>;
  /** Mensagem de erro */
  error: string;
  /** Stack trace, se disponível */
  stackTrace?: string;
  /** Número de tentativas realizadas */
  attempts: number;
  /** Total de retentativas via DLQ */
  dlqRetries: number;
  /** Timestamp da falha */
  failedAt: string;
  /** Opções originais do job */
  opts?: Record<string, unknown>;
}

/**
 * Estatísticas do DLQ de uma fila BullMQ.
 */
export interface DlqStats {
  /** Nome da fila */
  queueName: string;
  /** Tamanho atual do DLQ */
  dlqSize: number;
  /** Idade da entrada mais antiga em segundos */
  oldestEntryAge?: number;
  /** Se o threshold de alertas foi excedido */
  alertThresholdExceeded: boolean;
}

/**
 * Estatísticas de uma fila BullMQ.
 */
export interface QueueStats {
  /** Nome da fila */
  name: string;
  /** Jobs aguardando processamento */
  waiting: number;
  /** Jobs em processamento */
  active: number;
  /** Jobs concluídos */
  completed: number;
  /** Jobs falhos */
  failed: number;
  /** Jobs com atraso programado */
  delayed: number;
  /** Se a fila está pausada */
  paused: boolean;
}

/**
 * Resultado de verificação de rate limit.
 */
export interface RateLimitResult {
  /** Se a operação é permitida */
  allowed: boolean;
  /** Contagem atual na janela */
  current: number;
  /** Máximo permitido na janela */
  limit: number;
  /** Segundos até a janela resetar */
  resetInSeconds: number;
  /** Operações restantes na janela */
  remaining: number;
}

/**
 * Estatísticas do rate limiter de uma fila.
 */
export interface RateLimiterStats {
  /** Nome da fila */
  queueName: string;
  /** Contagem atual */
  current: number;
  /** Limite configurado */
  limit: number;
  /** Tamanho da janela em segundos */
  windowSeconds: number;
  /** Percentual de utilização */
  utilizationPercent: number;
}

/**
 * Resultado de uma tentativa de reprocessamento do DLQ.
 */
export interface ReprocessingResult {
  /** ID do job reprocessado */
  jobId: string;
  /** Nome da fila original */
  originalQueue: string;
  /** Se o reprocessamento foi bem-sucedido */
  success: boolean;
  /** Ação tomada */
  action: 'requeued' | 'max-retries-exceeded' | 'failed';
  /** Mensagem descritiva */
  message: string;
}

/**
 * Payload de notificação quando o máximo de retentativas é excedido.
 */
export interface MaxRetriesNotification {
  /** Nome da fila */
  queueName: string;
  /** ID do job no DLQ */
  jobId: string;
  /** ID do job original */
  originalJobId: string;
  /** Mensagem de erro */
  error: string;
  /** Total de tentativas realizadas */
  totalAttempts: number;
  /** Dados do job */
  data: Record<string, unknown>;
  /** Timestamp da notificação */
  timestamp: string;
}
