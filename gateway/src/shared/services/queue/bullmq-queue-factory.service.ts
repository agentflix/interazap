/**
 * Fábrica de filas BullMQ do gateway.
 *
 * Cria e gerencia instâncias de filas BullMQ com configuração
 * adequada para resiliência.
 */

import {
  Injectable,
  Logger,
  OnModuleDestroy,
  OnModuleInit,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { Queue, Worker, Job, QueueEvents } from 'bullmq';
import {
  BullMQQueueConfig,
  buildWorkerOptions,
  getDlqName,
  getBullMQQueueConfig,
} from './bullmq-resilience.config';
import { BullMQDlqService } from './bullmq-dlq.service';
import { DlqReprocessingWorker } from './dlq-reprocessing.worker';
import { QueueRateLimiterService } from './queue-rate-limiter.service';
import { QueueStats } from '../../models/queue.model';

export type { QueueStats };

/**
 * Tipo de função processadora de jobs do BullMQ.
 */
export type JobProcessor<T = unknown, R = unknown> = (
  job: Job<T>,
) => Promise<R>;

/**
 * Fábrica para criação e gerenciamento de filas BullMQ com resiliência.
 *
 * Responsible for creating queues, workers, and DLQ pairs with sensible defaults,
 * and for coordinating graceful shutdown of all BullMQ resources on module destroy.
 */
@Injectable()
export class BullMQQueueFactory implements OnModuleInit, OnModuleDestroy {
  private readonly logger = new Logger(BullMQQueueFactory.name);
  private readonly queues: Map<string, Queue> = new Map();
  private readonly workers: Map<string, Worker> = new Map();
  private readonly queueEvents: Map<string, QueueEvents> = new Map();
  private readonly redisUrl: string;

  /**
   * Initializes the factory with all required dependencies.
   *
   * @param configService - NestJS ConfigService for REDIS_URL
   * @param dlqService - BullMQDlqService used to register DLQ queues
   * @param dlqReprocessingWorker - Worker that reprocesses entries from DLQs
   * @param rateLimiter - QueueRateLimiterService for worker-side rate limiting
   */
  constructor(
    private readonly configService: ConfigService,
    private readonly dlqService: BullMQDlqService,
    private readonly dlqReprocessingWorker: DlqReprocessingWorker,
    private readonly rateLimiter: QueueRateLimiterService,
  ) {
    this.redisUrl =
      this.configService.get<string>('REDIS_URL') || 'redis://localhost:6379';
  }

  /**
   * Lifecycle hook called once when the NestJS module initialises.
   * Logs the startup of the factory; actual queue/worker creation is deferred
   * to {@link createQueue} and {@link createWorker} which domains call explicitly.
   */
  onModuleInit(): void {
    this.logger.log('Initializing BullMQ Queue Factory');
  }

  /**
   * Lifecycle hook called when the NestJS module is shutting down.
   * Gracefully closes all workers, queue events, queues, and the DLQ reprocessing
   * worker before clearing internal maps.
   */
  async onModuleDestroy(): Promise<void> {
    await this.shutdown();
  }

  /**
   * Cria uma nova fila BullMQ com configuração de resiliência.
   *
   * @param name - Nome da fila
   * @param customConfig - Configuração personalizada opcional
   * @returns Instância da fila BullMQ criada
   */
  createQueue<T = unknown>(
    name: string,
    customConfig?: Partial<BullMQQueueConfig>,
  ): Queue<T> {
    if (this.queues.has(name)) {
      return this.queues.get(name) as Queue<T>;
    }

    const config = {
      ...getBullMQQueueConfig(name),
      ...customConfig,
    };

    // Create main queue
    const queue = new Queue<T>(name, {
      connection: { url: this.redisUrl },
      defaultJobOptions: config.defaultJobOptions,
    });

    this.queues.set(name, queue);

    // Create DLQ for this queue
    const dlqName = getDlqName(name);
    const dlqQueue = new Queue(dlqName, {
      connection: { url: this.redisUrl },
      defaultJobOptions: {
        attempts: 1,
        removeOnComplete: false,
        removeOnFail: false,
      },
    });

    this.queues.set(dlqName, dlqQueue);
    this.dlqService.registerDlqQueue(name, dlqQueue);

    // Register with DLQ reprocessing worker
    this.dlqReprocessingWorker.registerQueue(queue);

    this.logger.log(`Created queue ${name} with DLQ ${dlqName}`);

    return queue;
  }

  /**
   * Cria um worker para uma fila com recursos de resiliência (DLQ, rate limiting).
   *
   * @param queueName - Nome da fila
   * @param processor - Função processadora dos jobs
   * @param customConfig - Configuração personalizada opcional
   * @returns Instância do worker BullMQ criado
   */
  createWorker<T = unknown, R = unknown>(
    queueName: string,
    processor: JobProcessor<T, R>,
    customConfig?: Partial<BullMQQueueConfig>,
  ): Worker<T, R> {
    if (this.workers.has(queueName)) {
      return this.workers.get(queueName) as Worker<T, R>;
    }

    const config = {
      ...getBullMQQueueConfig(queueName),
      ...customConfig,
    };

    const workerOptions = buildWorkerOptions(config);

    const worker = new Worker<T, R>(
      queueName,
      async (job) => {
        // Apply rate limiting if configured
        if (config.rateLimiter) {
          const result = await this.rateLimiter.consume(
            queueName,
            config.rateLimiter,
            job.id,
          );

          if (!result.allowed) {
            // Delay the job and retry
            await job.moveToDelayed(
              Date.now() + config.rateLimiter.duration / 2,
              job.token,
            );
            throw new Error('Rate limited, job delayed for retry');
          }
        }

        return processor(job);
      },
      {
        connection: { url: this.redisUrl },
        ...workerOptions,
      },
    );

    // Handle failed jobs - capture to DLQ
    worker.on('failed', (job, error) => {
      if (job && job.attemptsMade >= (job.opts.attempts || 5)) {
        void this.dlqService.captureFailedJob(job, error);
      }
    });

    worker.on('error', (error) => {
      this.logger.error(`Worker error on ${queueName}: ${error.message}`);
    });

    this.workers.set(queueName, worker);
    this.logger.log(`Created worker for ${queueName}`);

    return worker;
  }

  /**
   * Retorna uma fila existente pelo nome.
   *
   * @param name - Nome da fila
   * @returns Instância da fila ou undefined se não encontrada
   */
  getQueue<T = unknown>(name: string): Queue<T> | undefined {
    return this.queues.get(name) as Queue<T> | undefined;
  }

  /**
   * Retorna um worker existente pelo nome da fila.
   *
   * @param name - Nome da fila
   * @returns Instância do worker ou undefined se não encontrado
   */
  getWorker<T = unknown, R = unknown>(name: string): Worker<T, R> | undefined {
    return this.workers.get(name) as Worker<T, R> | undefined;
  }

  /**
   * Retorna estatísticas de uma fila (contadores de jobs por estado).
   *
   * @param name - Nome da fila
   * @returns Estatísticas da fila ou null se não encontrada
   */
  async getQueueStats(name: string): Promise<QueueStats | null> {
    const queue = this.queues.get(name);
    if (!queue) {
      return null;
    }

    try {
      const counts = await queue.getJobCounts();
      const isPaused = await queue.isPaused();

      return {
        name,
        waiting: counts.waiting || 0,
        active: counts.active || 0,
        completed: counts.completed || 0,
        failed: counts.failed || 0,
        delayed: counts.delayed || 0,
        paused: isPaused,
      };
    } catch (error) {
      this.logger.error(`Failed to get stats for ${name}`, error);
      return null;
    }
  }

  /**
   * Retorna estatísticas de todas as filas registradas (exclui DLQs).
   *
   * @returns Lista de estatísticas de todas as filas
   */
  async getAllQueueStats(): Promise<QueueStats[]> {
    const stats: QueueStats[] = [];

    for (const name of this.queues.keys()) {
      // Skip DLQ queues - they're handled separately
      if (!name.endsWith('-dlq')) {
        const queueStats = await this.getQueueStats(name);
        if (queueStats) {
          stats.push(queueStats);
        }
      }
    }

    return stats;
  }

  /**
   * Retorna os nomes de todas as filas registradas (exclui DLQs).
   *
   * @returns Lista de nomes de filas
   */
  getQueueNames(): string[] {
    return Array.from(this.queues.keys()).filter(
      (name) => !name.endsWith('-dlq'),
    );
  }

  /**
   * Pausa o processamento de uma fila.
   *
   * @param name - Nome da fila
   * @returns true se a fila foi pausada, false se não encontrada
   */
  async pauseQueue(name: string): Promise<boolean> {
    const queue = this.queues.get(name);
    if (!queue) {
      return false;
    }

    await queue.pause();
    this.logger.log(`Paused queue ${name}`);
    return true;
  }

  /**
   * Retoma o processamento de uma fila pausada.
   *
   * @param name - Nome da fila
   * @returns true se a fila foi retomada, false se não encontrada
   */
  async resumeQueue(name: string): Promise<boolean> {
    const queue = this.queues.get(name);
    if (!queue) {
      return false;
    }

    await queue.resume();
    this.logger.log(`Resumed queue ${name}`);
    return true;
  }

  /**
   * Esvazia uma fila removendo todos os jobs pendentes.
   *
   * @param name - Nome da fila
   * @param delayed - Se deve remover também jobs com delay agendado
   */
  async drainQueue(name: string, delayed: boolean = true): Promise<void> {
    const queue = this.queues.get(name);
    if (!queue) {
      return;
    }

    await queue.drain(delayed);
    this.logger.log(`Drained queue ${name}`);
  }

  /**
   * Remove jobs antigos de uma fila com base em age e limite.
   *
   * @param name - Nome da fila
   * @param grace - Período de grace em ms antes de remover (padrão: 1 hora)
   * @param status - Status dos jobs a remover ('completed' ou 'failed')
   * @param limit - Número máximo de jobs a remover
   * @returns Lista de IDs dos jobs removidos
   */
  async cleanQueue(
    name: string,
    grace: number = 3600000,
    status: 'completed' | 'failed' = 'completed',
    limit: number = 1000,
  ): Promise<string[]> {
    const queue = this.queues.get(name);
    if (!queue) {
      return [];
    }

    const cleaned = await queue.clean(grace, limit, status);
    this.logger.log(`Cleaned ${cleaned.length} ${status} jobs from ${name}`);
    return cleaned;
  }

  /**
   * Shutdown all queues and workers gracefully.
   */
  async shutdown(): Promise<void> {
    this.logger.log('Shutting down BullMQ queues and workers...');

    // Close workers first
    const workerClosePromises = Array.from(this.workers.values()).map(
      (worker) => worker.close(),
    );
    await Promise.all(workerClosePromises);

    // Close queue events
    const eventsClosePromises = Array.from(this.queueEvents.values()).map(
      (events) => events.close(),
    );
    await Promise.all(eventsClosePromises);

    // Close queues last
    const queueClosePromises = Array.from(this.queues.values()).map((queue) =>
      queue.close(),
    );
    await Promise.all(queueClosePromises);

    // Shutdown DLQ reprocessing worker
    await this.dlqReprocessingWorker.shutdown();

    this.workers.clear();
    this.queueEvents.clear();
    this.queues.clear();

    this.logger.log('All BullMQ resources shut down');
  }
}
