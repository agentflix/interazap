import {
  Controller,
  Get,
  Param,
  Post,
  HttpCode,
  Query,
  Delete,
  Body,
  UseGuards,
  UsePipes,
  ValidationPipe,
} from '@nestjs/common';
import { RedisService } from '../infrastructure/redis/redis.service';
import { InternalApiKeyGuard } from '../domains/realtime/guards/internal-api-key.guard';
import {
  StreamDlqService,
  QUEUE_CONFIGS,
  BullMQDlqService,
  BullMQQueueFactory,
  QueueRateLimiterService,
  BULLMQ_QUEUE_CONFIGS,
  DlqReprocessingWorker,
  STREAM_DLQ_ALERT_THRESHOLD,
} from '../shared/services/queue';

/**
 * Dashboard controller for queue management.
 *
 * Provides endpoints to monitor queue status, DLQ,
 * and perform administrative operations for both
 * Redis Streams and BullMQ queues.
 */
@Controller({ version: '1', path: 'admin/queues' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class QueueDashboardController {
  /**
   * Initializes the controller with queue management dependencies.
   *
   * @param redis              - Redis client for stream operations
   * @param streamDlqService   - Service for Redis Streams DLQ management
   * @param bullmqDlqService   - Service for BullMQ DLQ management
   * @param queueFactory       - Factory for BullMQ queue operations
   * @param rateLimiter        - Service for queue rate limiting
   * @param dlqReprocessingWorker - Worker for DLQ entry reprocessing
   */
  constructor(
    private readonly redis: RedisService,
    private readonly streamDlqService: StreamDlqService,
    private readonly bullmqDlqService: BullMQDlqService,
    private readonly queueFactory: BullMQQueueFactory,
    private readonly rateLimiter: QueueRateLimiterService,
    private readonly dlqReprocessingWorker: DlqReprocessingWorker,
  ) {}

  /**
   * Get overview of all queues (Redis Streams + BullMQ).
   */
  @Get()
  async getOverview() {
    // Get Redis Streams queues
    const streamQueues = await Promise.all(
      Object.keys(QUEUE_CONFIGS).map(async (name) => ({
        name,
        type: 'redis-streams' as const,
        size: await this.getStreamQueueSize(name),
        dlqSize: await this.streamDlqService.getSize(name),
        config: QUEUE_CONFIGS[name],
      })),
    );

    // Get BullMQ queues
    const bullmqStats = await this.queueFactory.getAllQueueStats();
    const bullmqDlqStats = await this.bullmqDlqService.getAllStats();

    const bullmqQueues = bullmqStats.map((stats) => {
      const dlqStats = bullmqDlqStats.find((d) => d.queueName === stats.name);
      return {
        name: stats.name,
        type: 'bullmq' as const,
        size: stats.waiting + stats.delayed,
        active: stats.active,
        completed: stats.completed,
        failed: stats.failed,
        dlqSize: dlqStats?.dlqSize || 0,
        paused: stats.paused,
        config: BULLMQ_QUEUE_CONFIGS[stats.name] || null,
      };
    });

    const totalStreamSize = streamQueues.reduce((sum, q) => sum + q.size, 0);
    const totalStreamDlq = streamQueues.reduce((sum, q) => sum + q.dlqSize, 0);
    const totalBullmqSize = bullmqQueues.reduce((sum, q) => sum + q.size, 0);
    const totalBullmqDlq = bullmqQueues.reduce((sum, q) => sum + q.dlqSize, 0);

    return {
      summary: {
        totalQueues: streamQueues.length + bullmqQueues.length,
        redisStreams: {
          count: streamQueues.length,
          pending: totalStreamSize,
          dlq: totalStreamDlq,
        },
        bullmq: {
          count: bullmqQueues.length,
          pending: totalBullmqSize,
          dlq: totalBullmqDlq,
        },
        totalPending: totalStreamSize + totalBullmqSize,
        totalDlq: totalStreamDlq + totalBullmqDlq,
      },
      redisStreams: streamQueues,
      bullmq: bullmqQueues,
      timestamp: new Date().toISOString(),
    };
  }

  /**
   * Get details for a specific Redis Streams queue.
   */
  @Get('streams/:name')
  async getStreamQueue(@Param('name') name: string) {
    const config = QUEUE_CONFIGS[name];
    const size = await this.getStreamQueueSize(name);
    const dlqSize = await this.streamDlqService.getSize(name);

    return {
      name,
      type: 'redis-streams',
      size,
      dlqSize,
      config: config || null,
      exists: config !== undefined,
    };
  }

  /**
   * Get details for a specific BullMQ queue.
   */
  @Get('bullmq/:name')
  async getBullMQQueue(@Param('name') name: string) {
    const stats = await this.queueFactory.getQueueStats(name);
    const dlqStats = await this.bullmqDlqService.getStats(name);
    const config = BULLMQ_QUEUE_CONFIGS[name];

    // Get rate limiter stats if configured
    let rateLimitStats: Awaited<
      ReturnType<typeof this.rateLimiter.getStats>
    > | null = null;
    if (config?.rateLimiter) {
      rateLimitStats = await this.rateLimiter.getStats(
        name,
        config.rateLimiter,
      );
    }

    return {
      name,
      type: 'bullmq',
      stats,
      dlq: dlqStats,
      rateLimiter: rateLimitStats,
      config: config || null,
      exists: stats !== null,
    };
  }

  /**
   * Get DLQ entries for a Redis Streams queue.
   */
  @Get('streams/:name/dlq')
  async getStreamDlqEntries(
    @Param('name') name: string,
    @Query('limit') limit: string = '100',
  ) {
    const entries = await this.streamDlqService.getPending(
      name,
      '.dlq',
      parseInt(limit, 10),
    );

    return {
      stream: name,
      type: 'redis-streams',
      count: entries.length,
      entries,
    };
  }

  /**
   * Get DLQ entries for a BullMQ queue.
   */
  @Get('bullmq/:name/dlq')
  async getBullMQDlqEntries(
    @Param('name') name: string,
    @Query('limit') limit: string = '100',
  ) {
    const entries = await this.bullmqDlqService.getEntries(
      name,
      parseInt(limit, 10),
    );

    return {
      queue: name,
      type: 'bullmq',
      count: entries.length,
      entries,
    };
  }

  /**
   * Retry a specific DLQ entry (Redis Streams).
   */
  @Post('streams/:name/dlq/:messageId/retry')
  @HttpCode(200)
  async retryStreamDlqEntry(
    @Param('name') name: string,
    @Param('messageId') messageId: string,
  ) {
    const success = await this.streamDlqService.retry(name, messageId);

    return {
      success,
      type: 'redis-streams',
      message: success
        ? `DLQ entry ${messageId} requeued successfully`
        : `Failed to requeue DLQ entry ${messageId}`,
    };
  }

  /**
   * Retry a specific DLQ entry (BullMQ).
   */
  @Post('bullmq/:name/dlq/:jobId/retry')
  @HttpCode(200)
  async retryBullMQDlqEntry(
    @Param('name') name: string,
    @Param('jobId') jobId: string,
  ) {
    const result = await this.dlqReprocessingWorker.reprocessEntry(name, jobId);

    return {
      success: result.success,
      type: 'bullmq',
      action: result.action,
      message: result.message,
    };
  }

  /**
   * Purge all DLQ entries for a BullMQ queue.
   */
  @Delete('bullmq/:name/dlq')
  @HttpCode(200)
  async purgeBullMQDlq(@Param('name') name: string) {
    const deleted = await this.bullmqDlqService.purge(name);

    return {
      success: true,
      type: 'bullmq',
      deleted,
      message: `Purged ${deleted} entries from DLQ`,
    };
  }

  /**
   * Pause a BullMQ queue.
   */
  @Post('bullmq/:name/pause')
  @HttpCode(200)
  async pauseBullMQQueue(@Param('name') name: string) {
    const success = await this.queueFactory.pauseQueue(name);

    return {
      success,
      message: success
        ? `Queue ${name} paused`
        : `Failed to pause queue ${name}`,
    };
  }

  /**
   * Resume a BullMQ queue.
   */
  @Post('bullmq/:name/resume')
  @HttpCode(200)
  async resumeBullMQQueue(@Param('name') name: string) {
    const success = await this.queueFactory.resumeQueue(name);

    return {
      success,
      message: success
        ? `Queue ${name} resumed`
        : `Failed to resume queue ${name}`,
    };
  }

  /**
   * Clean completed jobs from a BullMQ queue.
   */
  @Post('bullmq/:name/clean')
  @HttpCode(200)
  async cleanBullMQQueue(
    @Param('name') name: string,
    @Body() body: { grace?: number; status?: 'completed' | 'failed' },
  ) {
    const cleaned = await this.queueFactory.cleanQueue(
      name,
      body.grace || 3600000,
      body.status || 'completed',
    );

    return {
      success: true,
      cleaned: cleaned.length,
      message: `Cleaned ${cleaned.length} jobs from ${name}`,
    };
  }

  /**
   * Get DLQ stats for all queues.
   */
  @Get('dlq/stats')
  async getDlqStats() {
    const streamStats = await this.streamDlqService.getAllStats();
    const bullmqStats = await this.bullmqDlqService.getAllStats();

    const totalStream = Object.values(streamStats).reduce(
      (sum, count) => sum + count,
      0,
    );
    const totalBullmq = bullmqStats.reduce((sum, s) => sum + s.dlqSize, 0);

    const streamAlerts = Object.entries(streamStats)
      .filter(([, size]) => size >= STREAM_DLQ_ALERT_THRESHOLD)
      .map(([stream, size]) => ({
        stream,
        dlqSize: size,
        threshold: STREAM_DLQ_ALERT_THRESHOLD,
      }));

    return {
      redisStreams: streamStats,
      bullmq: bullmqStats,
      total: totalStream + totalBullmq,
      alerts: bullmqStats.filter((s) => s.alertThresholdExceeded),
      streamAlerts,
      timestamp: new Date().toISOString(),
    };
  }

  /**
   * Get rate limiter stats for all configured queues.
   */
  @Get('rate-limits')
  async getRateLimiterStats() {
    const configs: Record<string, { max: number; duration: number }> = {};

    for (const [name, config] of Object.entries(BULLMQ_QUEUE_CONFIGS)) {
      if (config.rateLimiter) {
        configs[name] = config.rateLimiter;
      }
    }

    const stats = await this.rateLimiter.getAllStats(configs);

    return {
      queues: stats,
      timestamp: new Date().toISOString(),
    };
  }

  /**
   * Get DLQ worker stats.
   */
  @Get('dlq/workers')
  getDlqWorkerStats() {
    const stats = this.dlqReprocessingWorker.getWorkerStats();

    return {
      workers: stats,
      timestamp: new Date().toISOString(),
    };
  }

  /**
   * Pause DLQ worker for a queue.
   */
  @Post('dlq/workers/:name/pause')
  @HttpCode(200)
  async pauseDlqWorker(@Param('name') name: string) {
    const success = await this.dlqReprocessingWorker.pauseWorker(name);

    return {
      success,
      message: success
        ? `DLQ worker for ${name} paused`
        : `Failed to pause DLQ worker for ${name}`,
    };
  }

  /**
   * Resume DLQ worker for a queue.
   */
  @Post('dlq/workers/:name/resume')
  @HttpCode(200)
  resumeDlqWorker(@Param('name') name: string) {
    const success = this.dlqReprocessingWorker.resumeWorker(name);

    return {
      success,
      message: success
        ? `DLQ worker for ${name} resumed`
        : `Failed to resume DLQ worker for ${name}`,
    };
  }

  private async getStreamQueueSize(streamName: string): Promise<number> {
    try {
      const client = this.redis.getClient();
      return (await client.xlen(streamName)) || 0;
    } catch {
      return 0;
    }
  }
}
