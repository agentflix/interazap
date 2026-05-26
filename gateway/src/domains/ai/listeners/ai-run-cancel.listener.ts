import {
  Injectable,
  Logger,
  OnModuleDestroy,
  OnModuleInit,
} from '@nestjs/common';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { GatewayConfigService } from '../../../shared/services/gateway-config.service';
import { AiCancellationRegistry } from '../ai-cancellation.registry';
import { toRecord } from '../../../shared/utils/payload-reader.util';

/**
 * Listener responsável por inscrever-se no canal Redis Pub/Sub de cancelamento de runs.
 *
 * Contexto: consome eventos publicados no canal configurado em `GatewayConfigService.aiRunCancelChannel`
 * e delega a persistência da flag de cancelamento ao `AiCancellationRegistry`.
 */
@Injectable()
export class AiRunCancelListener implements OnModuleInit, OnModuleDestroy {
  private readonly logger = new Logger(AiRunCancelListener.name);

  private readonly messageHandler = (
    channel: string,
    message: string,
  ): void => {
    if (channel !== this.gatewayConfigService.aiRunCancelChannel) {
      return;
    }

    try {
      const payload = toRecord(JSON.parse(message) as unknown);
      const rawRunId = payload['run_id'];
      const runId = (
        typeof rawRunId === 'string' || typeof rawRunId === 'number'
          ? String(rawRunId)
          : ''
      ).trim();

      if (runId === '') {
        return;
      }

      void this.registry
        .markCancelled(runId)
        .then(() => {
          this.logger.debug(`Marked run ${runId} as cancelled`);
        })
        .catch((error: unknown) => {
          this.logger.warn(
            `Failed to persist cancellation for run ${runId}: ${(error as Error).message}`,
          );
        });
    } catch (error) {
      this.logger.warn(
        `Failed to process cancel event: ${(error as Error).message}`,
      );
    }
  };

  constructor(
    private readonly redisService: RedisService,
    private readonly gatewayConfigService: GatewayConfigService,
    private readonly registry: AiCancellationRegistry,
  ) {}

  /**
   * Inscreve o listener no canal Redis Pub/Sub quando o módulo é inicializado.
   * @returns Promise resolvida após a subscrição ser confirmada pelo Redis.
   */
  async onModuleInit(): Promise<void> {
    const client = this.redisService.getPubSubClient();
    await client.subscribe(this.gatewayConfigService.aiRunCancelChannel);
    client.on('message', this.messageHandler);
  }

  /**
   * Remove o handler e cancela a subscrição no Redis quando o módulo é destruído.
   * @returns Promise resolvida após a desinscrição ser confirmada pelo Redis.
   */
  async onModuleDestroy(): Promise<void> {
    const client = this.redisService.getPubSubClient();
    client.off('message', this.messageHandler);
    await client.unsubscribe(this.gatewayConfigService.aiRunCancelChannel);
  }
}
