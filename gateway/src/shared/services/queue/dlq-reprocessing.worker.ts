/**
 * DLQ Reprocessing Worker.
 *
 * Processes failed jobs from DLQs and attempts to requeue them
 * to their original queues with proper delay and retry tracking.
 */

import { Injectable, Logger, OnModuleInit } from '@nestjs/common';
import { Job, Queue, Worker } from 'bullmq';
import { ConfigService } from '@nestjs/config';
import { BullMQDlqEntry, BullMQDlqService } from './bullmq-dlq.service';
import {
  getDlqName,
  getOriginalQueueName,
  MAX_TOTAL_DLQ_RETRIES,
} from './bullmq-resilience.config';
import {
  ReprocessingResult,
  MaxRetriesNotification,
} from '../../models/queue.model';

export type { ReprocessingResult, MaxRetriesNotification };

/**
 * Worker que processa entradas do DLQ e tenta o reprocessamento.
 */
@Injectable()
export class DlqReprocessingWorker implements OnModuleInit {
  private readonly logger = new Logger(DlqReprocessingWorker.name);
  private workers: Map<string, Worker> = new Map();
  private mainQueues: Map<string, Queue> = new Map();
  private notificationHandlers: Array<
    (notification: MaxRetriesNotification) => Promise<void>
  > = [];

  /**
   * Initializes the worker with the DLQ service and config service.
   *
   * @param dlqService - Service for DLQ operations
   * @param configService - NestJS config service for REDIS_URL
   */
  constructor(
    private readonly dlqService: BullMQDlqService,
    private readonly configService: ConfigService,
  ) {}

  /**
   * Lifecycle hook called when the module initializes.
   * Logs readiness; actual workers are created lazily via `registerQueue`.
   */
  onModuleInit(): void {
    // Workers are initialized when queues are registered
    this.logger.log('DLQ Reprocessing Worker initialized');
  }

  /**
   * Registra uma fila principal e inicia o worker do respectivo DLQ.
   *
   * @param queue - Instância da fila BullMQ principal
   */
  registerQueue(queue: Queue): void {
    this.mainQueues.set(queue.name, queue);

    const dlqName = getDlqName(queue.name);
    const redisUrl =
      this.configService.get<string>('REDIS_URL') || 'redis://localhost:6379';

    // Create worker for this DLQ
    const worker = new Worker<BullMQDlqEntry, ReprocessingResult>(
      dlqName,
      async (job) => this.processJob(job),
      {
        connection: {
          url: redisUrl,
        },
        concurrency: 1,
        limiter: {
          max: 10,
          duration: 60000, // 10 per minute
        },
      },
    );

    worker.on('completed', (job, result) => {
      this.logger.log(
        `DLQ job ${job.id} completed: ${result.action} - ${result.message}`,
      );
    });

    worker.on('failed', (job, error) => {
      this.logger.error(
        `DLQ job ${job?.id} processing failed: ${error.message}`,
        error.stack,
      );
    });

    this.workers.set(dlqName, worker);
    this.logger.log(`Started DLQ worker for ${queue.name}`);
  }

  /**
   * Processa uma única entrada do DLQ, tentando reenfileirar para a fila original.
   *
   * @param job - Job do BullMQ contendo a entrada DLQ
   * @returns Resultado do reprocessamento com ação executada
   */
  async processJob(job: Job<BullMQDlqEntry>): Promise<ReprocessingResult> {
    const entry = job.data;
    const originalQueue = this.mainQueues.get(entry.originalQueue);

    if (!originalQueue) {
      return {
        jobId: job.id || 'unknown',
        originalQueue: entry.originalQueue,
        success: false,
        action: 'failed',
        message: `Original queue ${entry.originalQueue} not registered`,
      };
    }

    // Check if max retries exceeded
    if (this.dlqService.isMaxRetriesExceeded(entry)) {
      await this.notifyMaxRetriesExceeded(entry, job.id || 'unknown');
      return {
        jobId: job.id || 'unknown',
        originalQueue: entry.originalQueue,
        success: true,
        action: 'max-retries-exceeded',
        message: `Max DLQ retries (${MAX_TOTAL_DLQ_RETRIES}) exceeded, notification sent`,
      };
    }

    try {
      // Prepare data with updated retry count
      const reprocessData = this.dlqService.prepareForReprocessing(entry);

      // Add back to original queue with delay
      await originalQueue.add('dlq-reprocess', reprocessData, {
        delay: this.dlqService.getReprocessingDelay(),
        attempts: 5, // Use default attempts for the reprocessed job
        backoff: {
          type: 'exponential',
          delay: 1000,
        },
      });

      this.logger.log(
        `Requeued job ${entry.originalJobId} to ${entry.originalQueue} (DLQ retry ${entry.dlqRetries + 1}/${MAX_TOTAL_DLQ_RETRIES})`,
      );

      return {
        jobId: job.id || 'unknown',
        originalQueue: entry.originalQueue,
        success: true,
        action: 'requeued',
        message: `Requeued to ${entry.originalQueue} with ${this.dlqService.getReprocessingDelay()}ms delay`,
      };
    } catch (error) {
      const errorMessage =
        error instanceof Error ? error.message : String(error);
      return {
        jobId: job.id || 'unknown',
        originalQueue: entry.originalQueue,
        success: false,
        action: 'failed',
        message: `Failed to requeue: ${errorMessage}`,
      };
    }
  }

  /**
   * Dispara o reprocessamento manual de uma entrada específica do DLQ.
   *
   * @param queueName - Nome da fila original
   * @param dlqJobId - ID do job no DLQ a ser reprocessado
   * @returns Resultado do reprocessamento
   */
  async reprocessEntry(
    queueName: string,
    dlqJobId: string,
  ): Promise<ReprocessingResult> {
    const dlqName = getDlqName(queueName);
    const entries = await this.dlqService.getEntries(queueName, 1000);

    // Find the entry - we need to get it from the queue directly
    const originalQueue = this.mainQueues.get(queueName);

    if (!originalQueue) {
      return {
        jobId: dlqJobId,
        originalQueue: queueName,
        success: false,
        action: 'failed',
        message: `Queue ${queueName} not registered`,
      };
    }

    // Get the DLQ queue
    const dlqQueueNames = this.dlqService.getRegisteredDlqNames();
    if (!dlqQueueNames.includes(dlqName)) {
      return {
        jobId: dlqJobId,
        originalQueue: queueName,
        success: false,
        action: 'failed',
        message: `DLQ ${dlqName} not found`,
      };
    }

    // Find the entry in our list
    const entry = entries.find((e) => e.originalJobId === dlqJobId);
    if (!entry) {
      return {
        jobId: dlqJobId,
        originalQueue: queueName,
        success: false,
        action: 'failed',
        message: `Entry ${dlqJobId} not found in DLQ`,
      };
    }

    if (this.dlqService.isMaxRetriesExceeded(entry)) {
      return {
        jobId: dlqJobId,
        originalQueue: queueName,
        success: false,
        action: 'max-retries-exceeded',
        message: `Max DLQ retries (${MAX_TOTAL_DLQ_RETRIES}) already exceeded`,
      };
    }

    try {
      const reprocessData = this.dlqService.prepareForReprocessing(entry);
      await originalQueue.add('manual-reprocess', reprocessData, {
        attempts: 5,
        backoff: { type: 'exponential', delay: 1000 },
      });

      // Delete from DLQ after successful requeue
      await this.dlqService.deleteEntry(queueName, dlqJobId);

      return {
        jobId: dlqJobId,
        originalQueue: queueName,
        success: true,
        action: 'requeued',
        message: `Manually requeued to ${queueName}`,
      };
    } catch (error) {
      const errorMessage =
        error instanceof Error ? error.message : String(error);
      return {
        jobId: dlqJobId,
        originalQueue: queueName,
        success: false,
        action: 'failed',
        message: `Manual reprocess failed: ${errorMessage}`,
      };
    }
  }

  /**
   * Registra um handler para ser notificado quando o limite máximo de tentativas DLQ for excedido.
   *
   * @param handler - Função assíncrona invocada com os detalhes da notificação
   */
  onMaxRetriesExceeded(
    handler: (notification: MaxRetriesNotification) => Promise<void>,
  ): void {
    this.notificationHandlers.push(handler);
  }

  /**
   * Notifica todos os handlers registrados sobre o estouro do limite de tentativas DLQ.
   *
   * @param entry - Entrada DLQ que excedeu o limite
   * @param dlqJobId - ID do job no DLQ
   */
  private async notifyMaxRetriesExceeded(
    entry: BullMQDlqEntry,
    dlqJobId: string,
  ): Promise<void> {
    const notification: MaxRetriesNotification = {
      queueName: entry.originalQueue,
      jobId: dlqJobId,
      originalJobId: entry.originalJobId,
      error: entry.error,
      totalAttempts: entry.attempts + entry.dlqRetries * 5, // Approximate total
      data: entry.data,
      timestamp: new Date().toISOString(),
    };

    this.logger.error(
      `Max DLQ retries exceeded for job ${entry.originalJobId} in queue ${entry.originalQueue}`,
      { notification },
    );

    for (const handler of this.notificationHandlers) {
      try {
        await handler(notification);
      } catch (error) {
        this.logger.error(
          `Notification handler failed: ${error}`,
          (error as Error).stack,
        );
      }
    }
  }

  /**
   * Retorna estatísticas de todos os workers DLQ registrados.
   *
   * @returns Lista com nome da fila, estado de execução e pause de cada worker
   */
  getWorkerStats(): Array<{
    queueName: string;
    isRunning: boolean;
    isPaused: boolean;
  }> {
    const stats: Array<{
      queueName: string;
      isRunning: boolean;
      isPaused: boolean;
    }> = [];

    for (const [dlqName, worker] of this.workers.entries()) {
      stats.push({
        queueName: getOriginalQueueName(dlqName),
        isRunning: worker.isRunning(),
        isPaused: worker.isPaused(),
      });
    }

    return stats;
  }

  /**
   * Pauses the DLQ worker for a given original queue.
   *
   * @param queueName - Name of the original queue whose DLQ worker should pause
   * @returns true if paused successfully, false if the worker was not found
   */
  async pauseWorker(queueName: string): Promise<boolean> {
    const dlqName = getDlqName(queueName);
    const worker = this.workers.get(dlqName);

    if (!worker) {
      return false;
    }

    await worker.pause();
    this.logger.log(`Paused DLQ worker for ${queueName}`);
    return true;
  }

  /**
   * Resumes the DLQ worker for a given original queue.
   *
   * @param queueName - Name of the original queue whose DLQ worker should resume
   * @returns true if resumed successfully, false if the worker was not found
   */
  resumeWorker(queueName: string): boolean {
    const dlqName = getDlqName(queueName);
    const worker = this.workers.get(dlqName);

    if (!worker) {
      return false;
    }

    worker.resume();
    this.logger.log(`Resumed DLQ worker for ${queueName}`);
    return true;
  }

  /**
   * Gracefully shutdown all workers.
   */
  async shutdown(): Promise<void> {
    this.logger.log('Shutting down DLQ workers...');

    const closePromises = Array.from(this.workers.values()).map((worker) =>
      worker.close(),
    );

    await Promise.all(closePromises);
    this.logger.log('All DLQ workers shut down');
  }
}
