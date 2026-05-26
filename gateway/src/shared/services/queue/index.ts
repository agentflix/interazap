/**
 * Índice do módulo de filas do gateway.
 *
 * Re-exporta todos os serviços, configurações e módulo de filas (Redis Streams e BullMQ).
 */
export * from './queue-resilience.config';
export * from './stream-dlq.service';
export * from './queue.module';
export * from './bullmq-resilience.config';
export * from './bullmq-dlq.service';
export * from './dlq-reprocessing.worker';
export * from './queue-rate-limiter.service';
export * from './bullmq-queue-factory.service';
