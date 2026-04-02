/**
 * Serviço de rate limiting para filas do gateway.
 *
 * Implementa rate limiting de janela deslizante para filas BullMQ
 * usando Redis como backing store.
 */

import { Injectable, Logger } from '@nestjs/common';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { QueueRateLimiter } from './bullmq-resilience.config';
import { RateLimitResult, RateLimiterStats } from '../../models/queue.model';

export type { RateLimitResult, RateLimiterStats };

/**
 * Rate limiter de janela deslizante para filas do gateway.
 *
 * Utiliza sorted sets do Redis para implementação eficiente de janela deslizante.
 */
@Injectable()
export class QueueRateLimiterService {
  private readonly logger = new Logger(QueueRateLimiterService.name);
  private readonly keyPrefix = 'queue:ratelimit:';

  /**
   * Initializes the service with a Redis client.
   *
   * @param redis - Redis service instance
   */
  constructor(private readonly redis: RedisService) {}

  /**
   * Verifica se uma operação é permitida pelo rate limit (sem consumir o slot).
   * Para consumir o slot, utilize o método `consume`.
   *
   * @param queueName - Nome da fila
   * @param config - Configuração do rate limiter
   * @returns Resultado da verificação de rate limit
   */
  async check(
    queueName: string,
    config: QueueRateLimiter,
  ): Promise<RateLimitResult> {
    const now = Date.now();
    const windowStart = now - config.duration;
    const key = this.getKey(queueName);

    try {
      const client = this.redis.getClient();

      // Remove old entries outside the window
      await client.zremrangebyscore(key, '-inf', windowStart);

      // Count current entries in the window
      const current = await client.zcard(key);
      const remaining = Math.max(0, config.max - current);
      const resetInSeconds = Math.ceil(config.duration / 1000);

      return {
        allowed: current < config.max,
        current,
        limit: config.max,
        resetInSeconds,
        remaining,
      };
    } catch (error) {
      this.logger.error(
        `Rate limit check failed for ${queueName}: ${error}`,
        (error as Error).stack,
      );
      // Fail open - allow operation if Redis is unavailable
      return {
        allowed: true,
        current: 0,
        limit: config.max,
        resetInSeconds: Math.ceil(config.duration / 1000),
        remaining: config.max,
      };
    }
  }

  /**
   * Consome um slot do rate limit.
   * Retorna o resultado da operação incluindo se foi permitida.
   *
   * @param queueName - Nome da fila
   * @param config - Configuração do rate limiter
   * @param identifier - Identificador único opcional para o slot
   * @returns Resultado da operação com status de permissão
   */
  async consume(
    queueName: string,
    config: QueueRateLimiter,
    identifier?: string,
  ): Promise<RateLimitResult> {
    const now = Date.now();
    const windowStart = now - config.duration;
    const key = this.getKey(queueName);
    const entryId =
      identifier || `${now}-${Math.random().toString(36).slice(2, 11)}`;

    try {
      const client = this.redis.getClient();

      // Use a multi/exec transaction for atomic operations
      const multi = client.multi();

      // Remove old entries
      multi.zremrangebyscore(key, '-inf', windowStart);

      // Count current entries
      multi.zcard(key);

      const results = await multi.exec();
      if (!results) {
        throw new Error('Transaction failed');
      }

      const current = (results[1]?.[1] as number) || 0;

      if (current >= config.max) {
        return {
          allowed: false,
          current,
          limit: config.max,
          resetInSeconds: Math.ceil(config.duration / 1000),
          remaining: 0,
        };
      }

      // Add new entry
      await client.zadd(key, now, entryId);

      // Set TTL to auto-cleanup
      await client.expire(key, Math.ceil(config.duration / 1000) + 10);

      return {
        allowed: true,
        current: current + 1,
        limit: config.max,
        resetInSeconds: Math.ceil(config.duration / 1000),
        remaining: Math.max(0, config.max - current - 1),
      };
    } catch (error) {
      this.logger.error(
        `Rate limit consume failed for ${queueName}: ${error}`,
        (error as Error).stack,
      );
      // Fail open
      return {
        allowed: true,
        current: 0,
        limit: config.max,
        resetInSeconds: Math.ceil(config.duration / 1000),
        remaining: config.max,
      };
    }
  }

  /**
   * Reseta o rate limit para uma fila.
   *
   * @param queueName - Nome da fila
   * @returns true se o reset foi bem-sucedido, false caso contrário
   */
  async reset(queueName: string): Promise<boolean> {
    const key = this.getKey(queueName);

    try {
      const client = this.redis.getClient();
      await client.del(key);
      this.logger.log(`Rate limit reset for ${queueName}`);
      return true;
    } catch (error) {
      this.logger.error(`Rate limit reset failed for ${queueName}`, error);
      return false;
    }
  }

  /**
   * Returns utilization statistics for a single queue's rate limiter.
   *
   * @param queueName - Name of the queue
   * @param config - Rate limiter configuration
   * @returns Statistics including current usage, limit, window, and utilization percent
   */
  async getStats(
    queueName: string,
    config: QueueRateLimiter,
  ): Promise<RateLimiterStats> {
    const result = await this.check(queueName, config);

    return {
      queueName,
      current: result.current,
      limit: result.limit,
      windowSeconds: Math.ceil(config.duration / 1000),
      utilizationPercent:
        result.limit > 0
          ? Math.round((result.current / result.limit) * 100)
          : 0,
    };
  }

  /**
   * Returns utilization statistics for all queues listed in the provided config map.
   *
   * @param configs - Record of queue names to rate limiter configurations
   * @returns Array of statistics, one per queue
   */
  async getAllStats(
    configs: Record<string, QueueRateLimiter>,
  ): Promise<RateLimiterStats[]> {
    const stats: RateLimiterStats[] = [];

    for (const [queueName, config] of Object.entries(configs)) {
      stats.push(await this.getStats(queueName, config));
    }

    return stats;
  }

  /**
   * Returns true when the queue has hit its rate limit ceiling.
   *
   * @param queueName - Name of the queue
   * @param config - Rate limiter configuration
   * @returns true if the current count equals or exceeds the limit
   */
  async isRateLimited(
    queueName: string,
    config: QueueRateLimiter,
  ): Promise<boolean> {
    const result = await this.check(queueName, config);
    return !result.allowed;
  }

  /**
   * Blocks until a rate limit slot becomes available or the timeout is reached.
   *
   * @param queueName - Name of the queue
   * @param config - Rate limiter configuration
   * @param timeoutMs - Maximum time to wait in milliseconds (default 30000)
   * @returns The consume result for the acquired slot
   * @throws Error when timeout is exceeded before a slot is available
   */
  async waitForSlot(
    queueName: string,
    config: QueueRateLimiter,
    timeoutMs: number = 30000,
  ): Promise<RateLimitResult> {
    const startTime = Date.now();

    while (Date.now() - startTime < timeoutMs) {
      const result = await this.consume(queueName, config);

      if (result.allowed) {
        return result;
      }

      // Wait a bit before retrying
      const waitTime = Math.min(1000, config.duration / 10);
      await this.sleep(waitTime);
    }

    throw new Error(
      `Rate limit timeout exceeded for ${queueName} after ${timeoutMs}ms`,
    );
  }

  /**
   * Builds the Redis key for a queue's rate limit counter.
   *
   * @param queueName - Name of the queue
   * @returns Full Redis key with the configured prefix
   */
  private getKey(queueName: string): string {
    return `${this.keyPrefix}${queueName}`;
  }

  /**
   * Resolves after the specified number of milliseconds.
   *
   * @param ms - Duration to sleep in milliseconds
   * @returns Promise that resolves after the delay
   */
  private sleep(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }
}
