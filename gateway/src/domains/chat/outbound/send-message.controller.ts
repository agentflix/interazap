import {
  Controller,
  Get,
  Param,
  Post,
  UseGuards,
  UsePipes,
  ValidationPipe,
} from '@nestjs/common';
import { StreamDlqService } from '../../../shared/services/queue/stream-dlq.service';
import { GatewayConfigService } from '../../../shared/services/gateway-config.service';
import { InternalApiKeyGuard } from '../../realtime/guards/internal-api-key.guard';

/**
 * SendMessageController
 *
 * Gerencia Dead Letter Queue (DLQ) para mensagens outbound.
 * Permite visualizar estatísticas e realizar retry de mensagens com falha.
 */
@Controller({ path: 'outbound', version: '1' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class SendMessageController {
  /**
   * Initializes the DLQ controller with required services.
   *
   * @param streamDlqService - Service for DLQ stream operations
   * @param gatewayConfigService - Service for gateway configuration including stream name
   */
  constructor(
    private readonly streamDlqService: StreamDlqService,
    private readonly gatewayConfigService: GatewayConfigService,
  ) {}

  /**
   * Returns the Redis stream name for chat outbound messages.
   */
  private get streamName(): string {
    return this.gatewayConfigService.chatOutboundStream;
  }

  /**
   * Returns DLQ statistics for the chat outbound stream.
   */
  @Get('dlq/stats')
  async getDlqStats() {
    const dlqSize = await this.streamDlqService.getSize(this.streamName);

    return {
      stream: this.streamName,
      dlqSize,
      timestamp: new Date().toISOString(),
    };
  }

  /**
   * Retries a failed message from the DLQ by messageId.
   *
   * @param messageId - ID of the message to retry
   */
  @Post('dlq/retry/:messageId')
  async retryFromDlq(@Param('messageId') messageId: string) {
    const success = await this.streamDlqService.retry(
      this.streamName,
      messageId,
    );

    return {
      stream: this.streamName,
      messageId,
      success,
    };
  }
}
