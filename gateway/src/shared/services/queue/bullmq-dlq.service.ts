/**
 * Serviço BullMQ para gerenciamento de Dead Letter Queue.
 *
 * Gerencia jobs com falha movendo-os para um DLQ para inspeção
 * e reprocessamento posterior.
 */

import { Injectable, Logger } from '@nestjs/common';
import { Job, Queue } from 'bullmq';
import {
  DLQ_ALERT_THRESHOLD,
  DLQ_REPROCESSING_DELAY,
  getDlqName,
  getOriginalQueueName,
  MAX_TOTAL_DLQ_RETRIES,
} from './bullmq-resilience.config';
import { BullMQDlqEntry, DlqStats } from '../../models/queue.model';

export type { BullMQDlqEntry, DlqStats };

/**
 * Serviço para gerenciamento de Dead Letter Queues do BullMQ.
 */
@Injectable()
export class BullMQDlqService {
  private readonly logger = new Logger(BullMQDlqService.name);
  private dlqQueues: Map<string, Queue> = new Map();

  /**
   * Inicializa o serviço DLQ. Nenhuma dependência injetada é necessária;
   * as filas DLQ individuais são registradas via {@link registerDlqQueue} na inicialização do módulo.
   */
  constructor() {}

  /**
   * Registra uma instância de fila DLQ.
   * Chamado durante a inicialização do módulo de filas.
   *
   * @param originalQueueName - Nome da fila original
   * @param dlqQueue - Instância da fila DLQ
   */
  registerDlqQueue(originalQueueName: string, dlqQueue: Queue): void {
    const dlqName = getDlqName(originalQueueName);
    this.dlqQueues.set(dlqName, dlqQueue);
    this.logger.log(`Registered DLQ: ${dlqName}`);
  }

  /**
   * Cria uma entrada DLQ a partir de um job com falha.
   *
   * @param job - Job do BullMQ que falhou
   * @param error - Erro que causou a falha
   * @returns Entrada formatada para o DLQ
   */
  createDlqEntry(job: Job, error: Error): BullMQDlqEntry {
    const jobData = job.data as Record<string, unknown> | undefined;
    const existingDlqRetries =
      (jobData?._dlqRetries as number | undefined) || 0;

    return {
      originalJobId: job.id || 'unknown',
      originalQueue: job.queueName,
      data: { ...jobData, _dlqRetries: undefined }, // Remove internal field
      error: error.message,
      stackTrace: error.stack,
      attempts: job.attemptsMade,
      dlqRetries: existingDlqRetries,
      failedAt: new Date().toISOString(),
      opts: job.opts as Record<string, unknown>,
    };
  }

  /**
   * Move um job com falha para seu DLQ.
   *
   * @param job - Job do BullMQ que falhou
   * @param error - Erro que causou a falha
   * @returns true se o job foi capturado com sucesso, false caso contrário
   */
  async captureFailedJob(job: Job, error: Error): Promise<boolean> {
    const dlqName = getDlqName(job.queueName);
    const dlqQueue = this.dlqQueues.get(dlqName);

    if (!dlqQueue) {
      this.logger.warn(
        `DLQ not registered for queue ${job.queueName}, job ${job.id} will not be captured`,
      );
      return false;
    }

    try {
      const entry = this.createDlqEntry(job, error);

      await dlqQueue.add('dlq-entry', entry, {
        attempts: 1,
        removeOnComplete: false,
        removeOnFail: false,
      });

      this.logger.warn(
        `Job ${job.id} from ${job.queueName} moved to DLQ after ${job.attemptsMade} attempts: ${error.message}`,
      );

      return true;
    } catch (dlqError) {
      this.logger.error(
        `Failed to capture job ${job.id} to DLQ: ${dlqError}`,
        (dlqError as Error).stack,
      );
      return false;
    }
  }

  /**
   * Retorna todas as entradas de um DLQ.
   *
   * @param queueName - Nome da fila original
   * @param limit - Número máximo de entradas a retornar
   * @returns Lista de entradas do DLQ
   */
  async getEntries(
    queueName: string,
    limit: number = 100,
  ): Promise<BullMQDlqEntry[]> {
    const dlqName = getDlqName(queueName);
    const dlqQueue = this.dlqQueues.get(dlqName);

    if (!dlqQueue) {
      return [];
    }

    try {
      const jobs = await dlqQueue.getJobs(
        ['waiting', 'delayed', 'failed'],
        0,
        limit,
      );

      return jobs
        .filter((job): job is Job => job !== undefined)
        .map((job) => job.data as BullMQDlqEntry);
    } catch (error) {
      this.logger.error(`Failed to get DLQ entries for ${queueName}`, error);
      return [];
    }
  }

  /**
   * Retorna o tamanho atual do DLQ de uma fila.
   *
   * @param queueName - Nome da fila original
   * @returns Número de jobs no DLQ
   */
  async getSize(queueName: string): Promise<number> {
    const dlqName = getDlqName(queueName);
    const dlqQueue = this.dlqQueues.get(dlqName);

    if (!dlqQueue) {
      return 0;
    }

    try {
      const counts = await dlqQueue.getJobCounts();
      return counts.waiting + counts.delayed + counts.failed;
    } catch {
      return 0;
    }
  }

  /**
   * Retorna estatísticas do DLQ de uma fila.
   *
   * @param queueName - Nome da fila original
   * @returns Estatísticas do DLQ
   */
  async getStats(queueName: string): Promise<DlqStats> {
    const size = await this.getSize(queueName);

    return {
      queueName,
      dlqSize: size,
      alertThresholdExceeded: size >= DLQ_ALERT_THRESHOLD,
    };
  }

  /**
   * Retorna estatísticas do DLQ de todas as filas registradas.
   *
   * @returns Lista de estatísticas de todos os DLQs
   */
  async getAllStats(): Promise<DlqStats[]> {
    const stats: DlqStats[] = [];

    for (const dlqName of this.dlqQueues.keys()) {
      const originalName = getOriginalQueueName(dlqName);
      const queueStats = await this.getStats(originalName);
      stats.push(queueStats);
    }

    return stats;
  }

  /**
   * Verifica se uma entrada do DLQ pode ser reprocessada.
   *
   * @param entry - Entrada do DLQ
   * @returns true se o número de tentativas DLQ ainda não atingiu o limite
   */
  canReprocess(entry: BullMQDlqEntry): boolean {
    return entry.dlqRetries < MAX_TOTAL_DLQ_RETRIES;
  }

  /**
   * Prepara uma entrada do DLQ para reprocessamento.
   * Retorna os dados do job com o contador de tentativas DLQ incrementado.
   *
   * @param entry - Entrada do DLQ a ser reprocessada
   * @returns Dados do job com campo interno `_dlqRetries` atualizado
   */
  prepareForReprocessing(
    entry: BullMQDlqEntry,
  ): Record<string, unknown> & { _dlqRetries: number } {
    return {
      ...entry.data,
      _dlqRetries: entry.dlqRetries + 1,
      _lastDlqError: entry.error,
      _lastDlqFailedAt: entry.failedAt,
    };
  }

  /**
   * Retorna o delay configurado para reprocessamento via DLQ.
   *
   * @returns Delay em milissegundos
   */
  getReprocessingDelay(): number {
    return DLQ_REPROCESSING_DELAY;
  }

  /**
   * Verifica se o limite máximo de tentativas via DLQ foi excedido.
   *
   * @param entry - Entrada do DLQ
   * @returns true se `dlqRetries` atingiu ou ultrapassou `MAX_TOTAL_DLQ_RETRIES`
   */
  isMaxRetriesExceeded(entry: BullMQDlqEntry): boolean {
    return entry.dlqRetries >= MAX_TOTAL_DLQ_RETRIES;
  }

  /**
   * Remove uma entrada específica do DLQ pelo ID do job.
   *
   * @param queueName - Nome da fila original
   * @param jobId - ID do job no DLQ a ser removido
   * @returns true se o job foi removido com sucesso
   */
  async deleteEntry(queueName: string, jobId: string): Promise<boolean> {
    const dlqName = getDlqName(queueName);
    const dlqQueue = this.dlqQueues.get(dlqName);

    if (!dlqQueue) {
      return false;
    }

    try {
      const job = await dlqQueue.getJob(jobId);
      if (job) {
        await job.remove();
        return true;
      }
      return false;
    } catch (error) {
      this.logger.error(`Failed to delete DLQ entry ${jobId}`, error);
      return false;
    }
  }

  /**
   * Remove todas as entradas do DLQ de uma fila.
   *
   * @param queueName - Nome da fila original
   * @returns Número de entradas removidas
   */
  async purge(queueName: string): Promise<number> {
    const dlqName = getDlqName(queueName);
    const dlqQueue = this.dlqQueues.get(dlqName);

    if (!dlqQueue) {
      return 0;
    }

    try {
      const jobs = await dlqQueue.getJobs(['waiting', 'delayed', 'failed']);
      let deleted = 0;

      for (const job of jobs) {
        if (job) {
          await job.remove();
          deleted++;
        }
      }

      this.logger.log(`Purged ${deleted} entries from ${dlqName}`);
      return deleted;
    } catch (error) {
      this.logger.error(`Failed to purge DLQ ${dlqName}`, error);
      return 0;
    }
  }

  /**
   * Retorna os nomes de todas as filas DLQ registradas.
   *
   * @returns Lista de nomes de filas DLQ
   */
  getRegisteredDlqNames(): string[] {
    return Array.from(this.dlqQueues.keys());
  }
}
