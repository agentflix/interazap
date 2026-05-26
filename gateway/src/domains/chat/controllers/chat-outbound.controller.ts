import {
  Body,
  BadRequestException,
  Controller,
  Logger,
  Post,
  UseGuards,
  UsePipes,
  ValidationPipe,
} from '@nestjs/common';
import { InternalApiKeyGuard } from '../../realtime/guards/internal-api-key.guard';
import { SendMessageService } from '../outbound/send-message.service';
import { OutboundMessageDto } from '../dto/outbound-message.dto';

/**
 * Controller de envio de mensagens outbound via provedores de chat.
 *
 * Contexto: expoe endpoint interno POST /outbound/send protegido por
 * InternalApiKeyGuard. Atualmente suporta apenas o provedor Z-API.
 * Delega o envio efetivo ao SendMessageService com circuit breaker.
 */
@Controller({ path: 'outbound', version: '1' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class ChatOutboundController {
  private readonly logger = new Logger(ChatOutboundController.name);

  /**
   * Inicializa o controller outbound com o servico de envio de mensagens.
   *
   * @param sendMessageService Servico responsavel por rotear e enviar mensagens outbound
   */
  constructor(private readonly sendMessageService: SendMessageService) {}

  /**
   * Envia uma mensagem outbound pelo provedor configurado.
   *
   * @param body Payload da mensagem outbound
   * @returns Resultado com indicador de sucesso, messageId e erro quando aplicavel
   * @throws BadRequestException Quando o provedor informado nao e suportado
   */
  @Post('send')
  async send(@Body() body: OutboundMessageDto) {
    this.logger.log(
      `Sending outbound message: provider=${body.provider}, to=${body.to}, type=${body.type}`,
    );

    if (body.provider !== 'zapi') {
      throw new BadRequestException(
        `Outbound provider not supported: ${body.provider}`,
      );
    }

    const result = await this.sendMessageService.send({
      tenantId: body.tenantId,
      instanceId: body.instanceId,
      provider: body.provider,
      instanceToken: body.instanceToken,
      type: body.type,
      to: body.to,
      text: body.text,
      mediaType: body.mediaType,
      mediaUrl: body.mediaUrl,
      caption: body.caption,
      fileName: body.fileName,
      correlationId: body.correlationId,
    });

    return {
      success: result.success,
      messageId: result.messageId,
      error: result.error,
    };
  }
}
