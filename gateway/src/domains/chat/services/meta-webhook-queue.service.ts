/**
 * MetaWebhookQueueService
 *
 * Fila BullMQ resiliente para processamento assíncrono de webhooks Meta.
 *
 * O controller valida a assinatura HMAC e enfileira o payload bruto ANTES de
 * devolver o ACK 200. O lookup de instância, a normalização do lote e a
 * publicação no Redis Stream acontecem no worker, com retry e DLQ providos
 * pelo `BullMQQueueFactory`. Falha de enqueue propaga erro — nunca produz
 * falso ACK.
 */

import { Injectable, Logger } from '@nestjs/common';
import { Queue } from 'bullmq';
import { BullMQQueueFactory } from '../../../shared/services/queue/bullmq-queue-factory.service';
import { MetaWebhookProcessor } from '../processors/meta-webhook.processor';

/** Payload do job de webhook Meta (payload bruto validado por HMAC). */
export type MetaWebhookQueueJob = {
  /** Payload bruto do webhook Meta (object + entry[]). */
  payload: Record<string, unknown>;
  /** Timestamp ISO de recebimento, para rastreabilidade. */
  receivedAt: string;
};

@Injectable()
export class MetaWebhookQueueService {
  static readonly META_WEBHOOK_QUEUE = 'meta-webhook-events';

  private readonly logger = new Logger(MetaWebhookQueueService.name);
  private queue: Queue<MetaWebhookQueueJob> | null = null;
  private workerInitialized = false;

  constructor(
    private readonly queueFactory: BullMQQueueFactory,
    private readonly processor: MetaWebhookProcessor,
  ) {}

  /**
   * Enfileira o payload bruto do webhook Meta para processamento assíncrono.
   *
   * @param payload - Payload bruto validado por HMAC
   * @throws Error quando a fila não está disponível — o caller deve NÃO devolver ACK
   */
  async enqueue(payload: Record<string, unknown>): Promise<void> {
    const queue = this.ensureQueue();

    await queue.add('process-meta-webhook', {
      payload,
      receivedAt: new Date().toISOString(),
    });

    this.logger.debug('Meta webhook payload enqueued for async processing');
  }

  /**
   * Inicializa fila e worker (lazy, na primeira chamada).
   * Fila durável com retry/DLQ via BullMQQueueFactory.
   */
  private ensureQueue(): Queue<MetaWebhookQueueJob> {
    if (!this.queue) {
      this.queue =
        this.queueFactory.createQueue<MetaWebhookQueueJob>(
          MetaWebhookQueueService.META_WEBHOOK_QUEUE,
        );
    }

    if (!this.workerInitialized) {
      this.queueFactory.createWorker<MetaWebhookQueueJob, void>(
        MetaWebhookQueueService.META_WEBHOOK_QUEUE,
        (job) => this.processor.process(job.data),
      );
      this.workerInitialized = true;
    }

    return this.queue;
  }
}
