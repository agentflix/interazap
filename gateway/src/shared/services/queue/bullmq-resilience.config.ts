/**
 * BullMQ resilience configuration.
 *
 * Centralized configuration for BullMQ queues with
 * retry policies, rate limiting, and DLQ settings.
 */

import { DefaultJobOptions, QueueOptions, WorkerOptions } from 'bullmq';
import { QueueRateLimiter, BullMQQueueConfig } from '../../models/queue.model';

export type { QueueRateLimiter, BullMQQueueConfig };

/**
 * Opções padrão de job aplicadas a todas as filas BullMQ.
 * Podem ser sobrescritas por fila.
 */
export const DEFAULT_JOB_OPTIONS: DefaultJobOptions = {
  attempts: 5,
  backoff: {
    type: 'exponential',
    delay: 1000,
  },
  removeOnComplete: {
    age: 3600, // 1 hour
    count: 1000,
  },
  removeOnFail: false, // Keep for DLQ processing
};

/**
 * Opções padrão de fila BullMQ.
 */
export const DEFAULT_QUEUE_OPTIONS: Partial<QueueOptions> = {
  defaultJobOptions: DEFAULT_JOB_OPTIONS,
};

/**
 * Configurações pré-definidas de filas BullMQ com políticas de retry e rate limiting.
 */
export const BULLMQ_QUEUE_CONFIGS: Record<string, BullMQQueueConfig> = {
  'internal-notifications': {
    name: 'internal-notifications',
    defaultJobOptions: {
      ...DEFAULT_JOB_OPTIONS,
      attempts: 3,
      backoff: {
        type: 'exponential',
        delay: 500,
      },
    },
    rateLimiter: {
      max: 100,
      duration: 60000, // 100 per minute
    },
    concurrency: 5,
    timeoutMs: 10000,
  },
  'ai-batch-processing': {
    name: 'ai-batch-processing',
    defaultJobOptions: {
      ...DEFAULT_JOB_OPTIONS,
      attempts: 3,
      backoff: {
        type: 'exponential',
        delay: 2000,
      },
    },
    rateLimiter: {
      max: 10,
      duration: 60000, // 10 per minute (AI rate limits)
    },
    concurrency: 2,
    timeoutMs: 180000, // 3 minutes for AI
  },
  'webhook-retries': {
    name: 'webhook-retries',
    defaultJobOptions: {
      ...DEFAULT_JOB_OPTIONS,
      attempts: 10,
      backoff: {
        type: 'exponential',
        delay: 5000,
      },
    },
    rateLimiter: {
      max: 50,
      duration: 60000, // 50 per minute
    },
    concurrency: 10,
    timeoutMs: 30000,
  },
  'data-export': {
    name: 'data-export',
    defaultJobOptions: {
      ...DEFAULT_JOB_OPTIONS,
      attempts: 2,
      backoff: {
        type: 'fixed',
        delay: 30000,
      },
    },
    concurrency: 1,
    timeoutMs: 600000, // 10 minutes
  },
  'dlq-reprocessing': {
    name: 'dlq-reprocessing',
    defaultJobOptions: {
      ...DEFAULT_JOB_OPTIONS,
      attempts: 1, // Single attempt for DLQ reprocessing
      delay: 60000, // 1 minute delay before processing
    },
    concurrency: 1,
    timeoutMs: 60000,
  },
};

/**
 * Sufixo utilizado para nomear filas DLQ no BullMQ.
 */
export const DLQ_SUFFIX = '-dlq';

/**
 * Retorna o nome do DLQ para uma fila.
 *
 * @param queueName - Nome da fila original
 * @returns Nome da fila DLQ com sufixo
 */
export function getDlqName(queueName: string): string {
  return `${queueName}${DLQ_SUFFIX}`;
}

/**
 * Verifica se um nome de fila pertence a um DLQ.
 *
 * @param queueName - Nome da fila a verificar
 * @returns true se for uma fila DLQ
 */
export function isDlqQueue(queueName: string): boolean {
  return queueName.endsWith(DLQ_SUFFIX);
}

/**
 * Retorna o nome da fila original a partir do nome do DLQ.
 *
 * @param dlqName - Nome da fila DLQ
 * @returns Nome da fila original sem o sufixo DLQ
 */
export function getOriginalQueueName(dlqName: string): string {
  if (isDlqQueue(dlqName)) {
    return dlqName.slice(0, -DLQ_SUFFIX.length);
  }
  return dlqName;
}

/**
 * Retorna a configuração BullMQ de uma fila pelo nome.
 * Aplica configuração padrão se a fila não possuir configuração pré-definida.
 *
 * @param queueName - Nome da fila
 * @returns Configuração completa da fila
 */
export function getBullMQQueueConfig(queueName: string): BullMQQueueConfig {
  return (
    BULLMQ_QUEUE_CONFIGS[queueName] || {
      name: queueName,
      defaultJobOptions: DEFAULT_JOB_OPTIONS,
      concurrency: 5,
      timeoutMs: 60000,
    }
  );
}

/**
 * Constrói as opções de worker BullMQ a partir da configuração da fila.
 *
 * @param config - Configuração da fila BullMQ
 * @returns Opções parciais de worker BullMQ
 */
export function buildWorkerOptions(
  config: BullMQQueueConfig,
): Partial<WorkerOptions> {
  const options: Partial<WorkerOptions> = {
    concurrency: config.concurrency,
    limiter: config.rateLimiter,
  };

  return options;
}

/**
 * Número máximo de tentativas totais via DLQ.
 * Após este limite acumulado, o job é considerado definitivamente morto.
 */
export const MAX_TOTAL_DLQ_RETRIES = 3;

/**
 * Delay em ms entre tentativas de reprocessamento via DLQ (5 minutos).
 */
export const DLQ_REPROCESSING_DELAY = 300000; // 5 minutos

/**
 * Limite de alerta para o tamanho do DLQ BullMQ.
 * Quando excedido, notificações devem ser enviadas.
 */
export const DLQ_ALERT_THRESHOLD = 100;
