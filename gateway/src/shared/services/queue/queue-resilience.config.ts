/**
 * Configuração de resiliência de filas para Redis Streams.
 *
 * Fornece configuração centralizada para políticas de retry,
 * rate limiting e configurações de dead letter queue.
 */
export type {
  RetryConfig,
  RateLimitConfig,
  DlqConfig,
  QueueConfig,
} from '../../models/queue.model';

import { RetryConfig, DlqConfig, QueueConfig } from '../../models/queue.model';

/**
 * Configuração padrão de retry com backoff exponencial.
 */
export const DEFAULT_RETRY_CONFIG: RetryConfig = {
  maxAttempts: 5,
  baseDelayMs: 1000,
  exponentialBase: 2,
  maxDelayMs: 300000, // 5 minutes
};

/**
 * Configuração padrão do Dead Letter Queue (DLQ).
 */
export const DEFAULT_DLQ_CONFIG: DlqConfig = {
  enabled: true,
  suffix: '.dlq',
  maxAgeSeconds: 604800, // 7 days
};

/**
 * Limite de alerta para o tamanho do DLQ nos Redis Streams.
 * Quando excedido, notificações devem ser enviadas.
 */
export const STREAM_DLQ_ALERT_THRESHOLD = Number(
  process.env.DLQ_ALERT_THRESHOLD ?? 100,
);

/**
 * Configurações pré-definidas de filas Redis Streams com políticas de retry e DLQ.
 */
export const QUEUE_CONFIGS: Record<string, QueueConfig> = {
  'chat.outbound_message': {
    stream: 'chat.outbound_message',
    group: 'gateway-outbound',
    retry: {
      maxAttempts: 5,
      baseDelayMs: 1000,
      exponentialBase: 2,
      maxDelayMs: 60000,
    },
    rateLimit: {
      maxOperations: 100,
      windowSeconds: 60,
    },
    dlq: DEFAULT_DLQ_CONFIG,
    timeoutMs: 30000,
  },
  'ai.chat': {
    stream: 'ai.chat',
    group: 'gateway-ai',
    retry: {
      maxAttempts: 3,
      baseDelayMs: 2000,
      exponentialBase: 3,
      maxDelayMs: 180000,
    },
    rateLimit: {
      maxOperations: 50,
      windowSeconds: 60,
    },
    dlq: DEFAULT_DLQ_CONFIG,
    timeoutMs: 180000, // 3 minutes for AI
  },
  'webhooks.outbound': {
    stream: 'webhooks.outbound',
    group: 'gateway-webhooks',
    retry: {
      maxAttempts: 10,
      baseDelayMs: 5000,
      exponentialBase: 2,
      maxDelayMs: 3600000, // 1 hour
    },
    dlq: DEFAULT_DLQ_CONFIG,
    timeoutMs: 30000,
  },
};

/**
 * Retorna a configuração de fila pelo nome do stream.
 * Aplica configuração padrão se o stream não possuir configuração pré-definida.
 *
 * @param streamName - Nome do stream Redis
 * @returns Configuração completa da fila com políticas de retry e DLQ
 */
export function getQueueConfig(streamName: string): QueueConfig {
  return (
    QUEUE_CONFIGS[streamName] || {
      stream: streamName,
      group: `gateway-${streamName.replace(/\./g, '-')}`,
      retry: DEFAULT_RETRY_CONFIG,
      dlq: DEFAULT_DLQ_CONFIG,
      timeoutMs: 60000,
    }
  );
}

/**
 * Calcula o delay de retry com backoff exponencial e jitter de ±10%.
 *
 * @param attempt - Número da tentativa (base 1)
 * @param config - Configuração de retry
 * @returns Delay em milissegundos com jitter aplicado, limitado ao máximo
 */
export function calculateRetryDelay(
  attempt: number,
  config: RetryConfig,
): number {
  const delay =
    config.baseDelayMs * Math.pow(config.exponentialBase, attempt - 1);
  const jitter = Math.random() * 0.2 * delay; // ±10% jitter
  return Math.min(delay + jitter, config.maxDelayMs);
}
