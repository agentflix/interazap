import { Injectable, Logger } from '@nestjs/common';
import { RedisService } from '../infrastructure/redis/redis.service';
import { HealthStatus, ServiceStatus } from './models/health.model';

/**
 * Serviço de health check do gateway.
 *
 * Contexto: módulo health. Monitora a conectividade do Redis e a presença
 * dos streams de consumer, retornando status consolidado de saúde do sistema.
 */
@Injectable()
export class HealthService {
  private readonly logger = new Logger(HealthService.name);

  constructor(private readonly redisService: RedisService) {}

  /**
   * Executa o health check completo em todos os serviços dependentes em paralelo.
   * @returns Status consolidado com timestamp e status individual de cada serviço
   */
  async checkAll(): Promise<HealthStatus> {
    const [redis, consumers] = await Promise.all([
      this.checkRedis(),
      this.checkConsumers(),
    ]);

    const services = { redis, consumers };
    const status = this.determineOverallStatus(services);

    return {
      status,
      timestamp: new Date().toISOString(),
      services,
    };
  }

  /**
   * Verifica a conectividade com o Redis via PING.
   * @returns Status healthy com latência medida, ou unhealthy com mensagem de erro
   */
  async checkRedis(): Promise<ServiceStatus> {
    const startTime = Date.now();

    try {
      const client = this.redisService.getClient();
      const pong = await client.ping();

      if (pong !== 'PONG') {
        return {
          status: 'unhealthy',
          message: 'Redis ping failed',
        };
      }

      const latency = Date.now() - startTime;

      return {
        status: 'healthy',
        latency_ms: latency,
      };
    } catch (error) {
      this.logger.error('Redis health check failed', error);

      return {
        status: 'unhealthy',
        message: error instanceof Error ? error.message : 'Unknown error',
      };
    }
  }

  /**
   * Verifica se os streams de consumer estão ativos via XINFO STREAM.
   * Inspeciona os streams principais do gateway e retorna status detalhado
   * com streams ativos e eventuais erros de inspeção.
   * @returns Status healthy/unhealthy com detalhes de cada stream monitorado
   */
  async checkConsumers(): Promise<ServiceStatus> {
    const startTime = Date.now();

    try {
      // Check for active stream consumers by looking at stream info
      const streams = [
        'chat.inbound_message_received',
        'chat.outbound_message',
        'billing.payment_received',
        'ai.run.request',
        'ai.chat_request',
        'ai.embedding_request',
      ];

      const streamChecks = await Promise.all(
        streams.map((stream) => this.checkStreamExists(stream)),
      );
      const activeStreams = streamChecks
        .filter((streamCheck) => streamCheck.active)
        .map((streamCheck) => streamCheck.stream);
      const streamErrors = streamChecks
        .filter((streamCheck) => streamCheck.error !== undefined)
        .map((streamCheck) => ({
          stream: streamCheck.stream,
          error: streamCheck.error,
        }));
      const hasUnexpectedStreamErrors = streamErrors.length > 0;

      const latency = Date.now() - startTime;

      if (hasUnexpectedStreamErrors) {
        this.logger.warn(
          'Consumer health check detected stream inspection errors',
          {
            streamErrors,
          },
        );
      }

      return {
        status: hasUnexpectedStreamErrors ? 'unhealthy' : 'healthy',
        latency_ms: latency,
        message: hasUnexpectedStreamErrors
          ? 'One or more streams could not be inspected'
          : undefined,
        details: {
          monitored_streams: streams,
          active_streams: activeStreams,
          stream_errors: streamErrors,
        },
      };
    } catch (error) {
      this.logger.error('Consumer health check failed', error);

      return {
        status: 'unhealthy',
        message: error instanceof Error ? error.message : 'Unknown error',
      };
    }
  }

  /**
   * Determina o status geral com base nos status individuais de cada serviço.
   * - Nenhum unhealthy → healthy
   * - Todos unhealthy → unhealthy
   * - Parcial → degraded
   * @param services Mapa de nome do serviço para seu ServiceStatus
   * @returns Status consolidado: healthy, degraded ou unhealthy
   */
  private determineOverallStatus(
    services: Record<string, ServiceStatus>,
  ): 'healthy' | 'degraded' | 'unhealthy' {
    const statuses = Object.values(services);
    const unhealthyCount = statuses.filter(
      (s) => s.status === 'unhealthy',
    ).length;

    if (unhealthyCount === 0) {
      return 'healthy';
    }

    if (unhealthyCount === statuses.length) {
      return 'unhealthy';
    }

    return 'degraded';
  }

  /**
   * Verifica se um stream específico existe via XINFO STREAM, isolando falhas
   * para não impactar a verificação dos demais streams.
   * @param stream Nome do stream a verificar
   * @returns Objeto com `stream`, `active` e `error` opcional
   */
  private async checkStreamExists(stream: string): Promise<{
    stream: string;
    active: boolean;
    error?: string;
  }> {
    const client = this.redisService.getClient();

    try {
      const info = await client.xinfo('STREAM', stream);
      return {
        stream,
        active: Boolean(info),
      };
    } catch (error: unknown) {
      const message = error instanceof Error ? error.message : String(error);

      if (message.includes('no such key')) {
        return {
          stream,
          active: false,
        };
      }

      return {
        stream,
        active: false,
        error: message,
      };
    }
  }
}
