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
 * Controller de gerenciamento da Dead Letter Queue (DLQ) para mensagens outbound.
 *
 * Contexto: expoe endpoints internos protegidos por InternalApiKeyGuard para
 * visualizar estatisticas da DLQ e realizar retry de mensagens que falharam
 * no Redis Stream de saida do chat.
 */
@Controller({ path: 'outbound', version: '1' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class SendMessageController {
  /**
   * Inicializa o controller de DLQ com os servicos necessarios.
   *
   * @param streamDlqService Servico para operacoes de stream DLQ
   * @param gatewayConfigService Servico de configuracao do gateway incluindo nome do stream
   */
  constructor(
    private readonly streamDlqService: StreamDlqService,
    private readonly gatewayConfigService: GatewayConfigService,
  ) {}

  /** Retorna o nome do Redis Stream para mensagens outbound do chat. */
  private get streamName(): string {
    return this.gatewayConfigService.chatOutboundStream;
  }

  /**
   * Retorna estatisticas da DLQ para o stream outbound do chat.
   *
   * @returns Objeto com nome do stream, tamanho da DLQ e timestamp
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
   * Reenvia uma mensagem com falha da DLQ pelo seu ID.
   *
   * @param messageId ID da mensagem a reenviar
   * @returns Objeto com nome do stream, messageId e indicador de sucesso
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
