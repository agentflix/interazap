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

@Injectable()
export class AiRunCancelListener implements OnModuleInit, OnModuleDestroy {
  private readonly logger = new Logger(AiRunCancelListener.name);

  private readonly messageHandler = (channel: string, message: string): void => {
    if (channel !== this.gatewayConfigService.aiRunCancelChannel) {
      return;
    }

    try {
      const payload = toRecord(JSON.parse(message) as unknown);
      const runId = String(payload['run_id'] ?? '').trim();

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

  async onModuleInit(): Promise<void> {
    const client = this.redisService.getPubSubClient();
    await client.subscribe(this.gatewayConfigService.aiRunCancelChannel);
    client.on('message', this.messageHandler);
  }

  async onModuleDestroy(): Promise<void> {
    const client = this.redisService.getPubSubClient();
    client.off('message', this.messageHandler);
    await client.unsubscribe(this.gatewayConfigService.aiRunCancelChannel);
  }
}
