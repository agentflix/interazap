import { Global, Module } from '@nestjs/common';
import { StreamDlqService } from './stream-dlq.service';
import { BullMQDlqService } from './bullmq-dlq.service';
import { DlqReprocessingWorker } from './dlq-reprocessing.worker';
import { QueueRateLimiterService } from './queue-rate-limiter.service';
import { BullMQQueueFactory } from './bullmq-queue-factory.service';

/**
 * Módulo global de resiliência de filas do gateway.
 *
 * Disponibiliza serviços de gerenciamento de filas, tratamento de DLQ
 * e monitoramento de filas Redis Streams e BullMQ.
 */
@Global()
@Module({
  providers: [
    StreamDlqService,
    BullMQDlqService,
    DlqReprocessingWorker,
    QueueRateLimiterService,
    BullMQQueueFactory,
  ],
  exports: [
    StreamDlqService,
    BullMQDlqService,
    DlqReprocessingWorker,
    QueueRateLimiterService,
    BullMQQueueFactory,
  ],
})
export class QueueModule {}
