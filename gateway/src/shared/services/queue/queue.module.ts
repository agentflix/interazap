import { Global, Module } from '@nestjs/common';
import { StreamDlqService } from './stream-dlq.service';
import { BullMQDlqService } from './bullmq-dlq.service';
import { DlqReprocessingWorker } from './dlq-reprocessing.worker';
import { QueueRateLimiterService } from './queue-rate-limiter.service';
import { BullMQQueueFactory } from './bullmq-queue-factory.service';

/**
 * Queue Resilience Module.
 *
 * Provides services for queue management, DLQ handling,
 * and monitoring of both Redis Streams and BullMQ queues.
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
