import {
  Controller,
  Get,
  Post,
  Query,
  Headers,
  Req,
  ForbiddenException,
  Logger,
  Body,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import type { Request } from 'express';
import * as crypto from 'crypto';
import { ChatWebhookService } from '../services/chat-webhook.service';
import { WebhookEventDto } from '../dto/webhook-event.dto';
import { MetaWebhookPayload } from '../contracts/meta-provider.interface';
import { MetaWebhookDto } from '../dto/meta-webhook.dto';
import { BadRequestException } from '@nestjs/common';

/**
 * MetaWebhookController
 *
 * Controller dedicado para webhooks da Meta WhatsApp Business API.
 * Gerencia handshake de verificacao e validacao HMAC de eventos.
 */
@Controller({ version: '1', path: 'webhooks/meta' })
export class MetaWebhookController {
  private readonly logger = new Logger(MetaWebhookController.name);

  constructor(
    private readonly configService: ConfigService,
    private readonly chatWebhookService: ChatWebhookService,
  ) {}

  /**
   * Handshake de verificacao do webhook.
   * GET /webhooks/meta?hub.mode=subscribe&hub.verify_token=xxx&hub.challenge=xxx
   */
  @Get()
  verifyWebhook(
    @Query('hub.mode') mode: string,
    @Query('hub.verify_token') token: string,
    @Query('hub.challenge') challenge: string,
  ): string {
    const verifyToken =
      this.configService.get<string>('meta.verifyToken') ?? '';

    this.logger.debug(`Webhook verification request: mode=${mode}`);

    if (mode === 'subscribe' && token === verifyToken) {
      this.logger.log('Webhook verification successful');
      return challenge;
    }

    this.logger.warn('Invalid webhook verification request');
    throw new ForbiddenException('Invalid verification token');
  }

  /**
   * Recebe eventos de webhook da Meta com validacao HMAC.
   * POST /webhooks/meta
   */
  @Post()
  async handleWebhook(
    @Headers('x-hub-signature-256') signature: string,
    @Body() payload: MetaWebhookDto,
    @Req() req: Request,
  ): Promise<{ success: boolean }> {
    const appSecret = this.configService.get<string>('meta.appSecret') ?? '';

    // 1. Valida assinatura HMAC
    if (!signature) {
      this.logger.warn('Missing X-Hub-Signature-256 header');
      throw new ForbiddenException('Missing signature');
    }

    // 2. Obtem body bruto para calculo do HMAC
    const rawBody =
      typeof req.body === 'string' ? req.body : JSON.stringify(req.body);

    // 3. Calcula a assinatura esperada
    const expectedSig = `sha256=${crypto
      .createHmac('sha256', appSecret)
      .update(rawBody, 'utf-8')
      .digest('hex')}`;

    // 4. Compara assinaturas usando comparacao de tempo constante (timing-safe)
    const signatureBuffer = Buffer.from(signature);
    const expectedBuffer = Buffer.from(expectedSig);

    if (
      signatureBuffer.length !== expectedBuffer.length ||
      !crypto.timingSafeEqual(signatureBuffer, expectedBuffer)
    ) {
      this.logger.warn('Invalid webhook signature');
      throw new ForbiddenException('Invalid signature');
    }

    this.logger.debug('Webhook HMAC signature verified');

    // 5. Extrai phone_number_id do payload para roteamento
    const phoneNumberId =
      payload.entry?.[0]?.changes?.[0]?.value?.metadata?.phone_number_id;

    if (!phoneNumberId) {
      this.logger.warn('Webhook payload missing phone_number_id for routing');
      throw new BadRequestException(
        'Invalid payload: missing metadata.phone_number_id',
      );
    }

    // 6. Constroi o evento de webhook
    const event: WebhookEventDto = {
      event_type: 'messages',
      raw: payload as unknown as Record<string, unknown>,
    };

    // 7. Encaminha para o ChatWebhookService para processamento
    // Nota: phoneNumberId e usado como webhookToken para a Meta pois ela nao usa tokens na URL
    await this.chatWebhookService.handle(
      'meta',
      phoneNumberId, // phone_number_id usado como token para lookup
      event,
      null,
    );

    return { success: true };
  }
}
