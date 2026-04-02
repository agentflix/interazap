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
 * ChatOutboundController
 *
 * Controla o envio de mensagens outbound via providers
 * (atualmente suporta ZAPI).
 */
@Controller({ path: 'outbound', version: '1' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class ChatOutboundController {
  private readonly logger = new Logger(ChatOutboundController.name);

  /**
   * Initializes the outbound controller with the send message service.
   *
   * @param sendMessageService - Service responsible for routing and sending outbound messages
   */
  constructor(private readonly sendMessageService: SendMessageService) {}

  /**
   * Sends an outbound message via the configured provider.
   *
   * @param body - Outbound message payload
   * @returns Result with success status, messageId and error
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
