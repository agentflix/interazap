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
 * Controller de dashboard administrativo para gerenciamento de filas.
 *
 * Contexto: módulo health. Expõe endpoints para monitorar status, DLQ
 * e executar operações administrativas em filas Redis Streams e BullMQ.
 * Protegido pelo guard de API key interna.
 */
@Controller({ version: '1', path: 'admin/queues' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class QueueDashboardController {
  constructor(
    private readonly redis: RedisService,
    private readonly streamDlqService: StreamDlqService,
    private readonly bullmqDlqService: BullMQDlqService,
    private readonly queueFactory: BullMQQueueFactory,
    private readonly rateLimiter: QueueRateLimiterService,
    private readonly dlqReprocessingWorker: DlqReprocessingWorker,
  ) {}

  /**
   * GET /admin/queues
   * Retorna visão geral de todas as filas (Redis Streams + BullMQ) com tamanho e DLQ.
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
   * GET /admin/queues/streams/:name
   * Retorna detalhes de um stream Redis específico: tamanho, DLQ e configuração.
   * @param name Nome do stream
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
   * GET /admin/queues/bullmq/:name
   * Retorna detalhes de uma fila BullMQ específica: stats, DLQ, rate limiter e configuração.
   * @param name Nome da fila
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
   * GET /admin/queues/streams/:name/dlq
   * Lista entradas na DLQ de um stream Redis.
   * @param name Nome do stream
   * @param limit Número máximo de entradas a retornar (padrão: 100)
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
   * GET /admin/queues/bullmq/:name/dlq
   * Lista entradas na DLQ de uma fila BullMQ.
   * @param name Nome da fila
   * @param limit Número máximo de entradas a retornar (padrão: 100)
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
   * POST /admin/queues/streams/:name/dlq/:messageId/retry
   * Reenfileira uma entrada específica da DLQ de um stream Redis.
   * @param name Nome do stream
   * @param messageId ID da mensagem na DLQ
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
   * POST /admin/queues/bullmq/:name/dlq/:jobId/retry
   * Reprocessa uma entrada específica da DLQ de uma fila BullMQ.
   * @param name Nome da fila
   * @param jobId ID do job na DLQ
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
   * DELETE /admin/queues/bullmq/:name/dlq
   * Remove todas as entradas da DLQ de uma fila BullMQ.
   * @param name Nome da fila
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
   * POST /admin/queues/bullmq/:name/pause
   * Pausa o processamento de uma fila BullMQ.
   * @param name Nome da fila
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
   * POST /admin/queues/bullmq/:name/resume
   * Retoma o processamento de uma fila BullMQ pausada.
   * @param name Nome da fila
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
   * POST /admin/queues/bullmq/:name/clean
   * Remove jobs completados (ou com falha) de uma fila BullMQ.
   * @param name Nome da fila
   * @param body `grace` (ms de graça, padrão 3600000) e `status` (completed|failed)
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
   * GET /admin/queues/dlq/stats
   * Retorna estatísticas consolidadas de DLQ para todas as filas (streams e BullMQ).
   * Inclui alertas quando o tamanho da DLQ excede o threshold configurado.
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
   * GET /admin/queues/rate-limits
   * Retorna estatísticas de rate limiter para todas as filas BullMQ configuradas.
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
   * GET /admin/queues/dlq/workers
   * Retorna estatísticas dos workers de reprocessamento da DLQ.
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
   * POST /admin/queues/dlq/workers/:name/pause
   * Pausa o worker de reprocessamento da DLQ para uma fila específica.
   * @param name Nome da fila
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
   * POST /admin/queues/dlq/workers/:name/resume
   * Retoma o worker de reprocessamento da DLQ para uma fila específica.
   * @param name Nome da fila
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

  /**
   * Retorna o número de entradas em um Redis Stream via XLEN.
   * Retorna 0 silenciosamente em caso de erro para não impactar o overview.
   * @param streamName Nome do stream
   * @returns Número de entradas no stream
   */
  private async getStreamQueueSize(streamName: string): Promise<number> {
    try {
      const client = this.redis.getClient();
      return (await client.xlen(streamName)) || 0;
    } catch {
      return 0;
    }
  }
}
