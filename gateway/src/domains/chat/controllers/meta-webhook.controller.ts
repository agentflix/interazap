import {
  Controller,
  Get,
  Post,
  Query,
  Headers,
  Req,
  ForbiddenException,
  InternalServerErrorException,
  Logger,
  Body,
  HttpCode,
  HttpStatus,
  type RawBodyRequest,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import type { Request } from 'express';
import * as crypto from 'crypto';
import { MetaConfigService } from '../providers/meta/meta.config';
import { MetaWebhookQueueService } from '../services/meta-webhook-queue.service';

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
    private readonly metaConfig: MetaConfigService,
    private readonly metaWebhookQueue: MetaWebhookQueueService,
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
    // Fail-closed: sem META_VERIFY_TOKEN configurado, o handshake nunca é aceito.
    if (!this.metaConfig.isConfigured()) {
      this.logger.error(
        'Webhook verification rejected: META_VERIFY_TOKEN/META_APP_SECRET not configured',
      );
      throw new ForbiddenException('Webhook not configured');
    }

    const verifyToken = this.configService.get<string>('meta.verifyToken') ?? '';

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
   *
   * O `@Body()` é tipado como `Record<string, unknown>` (NÃO uma classe DTO)
   * de proposito: o `ValidationPipe` global (`main.ts`) roda `whitelist` +
   * `forbidNonWhitelisted` apenas quando o metatype declarado é uma classe.
   * Um DTO parcial aqui faria o pipe (a) rejeitar com 400 qualquer payload
   * real da Meta que traga campos não declarados (`value.messages`,
   * `value.statuses`, etc. — exatamente o 4xx que a Meta usa como gatilho de
   * reentrega em loop), ou (b) silenciosamente remover esses campos do body
   * antes do handler rodar, descartando toda mensagem sem erro nenhum. Mesmo
   * padrão de `ChatWebhookController` (`chat-webhook.controller.ts`).
   *
   * Após a assinatura ser validada, o payload é ENFILEIRADO numa fila BullMQ
   * durável (retry/DLQ) e o ACK 200 é devolvido — o lookup de instância,
   * normalização e publicação no Redis Stream acontecem no processor
   * assíncrono. Falha de enqueue lança 500 — nunca um falso ACK.
   *
   * Payloads sem forma mínima ou sem eventos resolvíveis são apenas
   * logados/descartados no processor, nunca um 4xx que provocaria reentrega
   * em loop da Meta.
   */
  @Post()
  @HttpCode(HttpStatus.OK)
  async handleWebhook(
    @Headers('x-hub-signature-256') signature: string,
    @Body() rawPayload: Record<string, unknown>,
    @Req() req: RawBodyRequest<Request>,
  ): Promise<{ success: boolean }> {
    // Fail-closed: sem META_APP_SECRET, a assinatura nunca é validada com chave vazia.
    if (!this.metaConfig.isConfigured()) {
      this.logger.error(
        'Webhook rejected: META_APP_SECRET/META_VERIFY_TOKEN not configured',
      );
      throw new ForbiddenException('Webhook not configured');
    }

    const appSecret = this.configService.get<string>('meta.appSecret') ?? '';

    // 1. Valida assinatura HMAC
    if (!signature) {
      this.logger.warn('Missing X-Hub-Signature-256 header');
      throw new ForbiddenException('Missing signature');
    }

    // 2. Calcula o HMAC sobre o corpo BRUTO da requisicao (req.rawBody), nunca
    // sobre JSON.stringify(req.body) — o body-parser pode reordenar chaves e
    // quebrar a assinatura calculada pela Meta sobre os bytes originais.
    const rawBody = req.rawBody ?? Buffer.from(JSON.stringify(rawPayload));

    // 3. Calcula a assinatura esperada
    const expectedSig = `sha256=${crypto
      .createHmac('sha256', appSecret)
      .update(rawBody)
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

    // 5. Checagem de forma minima — nunca lanca 4xx, so ACK+log quando o
    // payload nao parece um webhook da Meta (defesa contra corpo malformado
    // que o ValidationPipe generico deixaria passar sem erro).
    if (!this.hasMinimalMetaShape(rawPayload)) {
      this.logger.warn(
        'Meta webhook payload missing object/entry[] — acking without processing',
      );
      return { success: true };
    }

    // 6. Enfileira o payload validado para processamento assíncrono.
    // O ACK só é devolvido após o enqueue durável — falha aqui NÃO produz
    // falso ACK (a Meta reentregaria o evento até a fila aceitar).
    try {
      await this.metaWebhookQueue.enqueue(rawPayload);
    } catch (error) {
      this.logger.error(
        `Failed to enqueue Meta webhook payload: ${error instanceof Error ? error.message : String(error)}`,
        error instanceof Error ? error.stack : undefined,
      );
      throw new InternalServerErrorException('Failed to enqueue webhook event');
    }

    return { success: true };
  }

  /**
   * Checagem de forma mínima do payload — apenas o suficiente para descartar
   * corpos claramente malformados sem exigir um shape completo (isso é papel
   * do `MetaWebhookPayload`/`MetaProvider.normalizeAll`, não deste guard).
   */
  private hasMinimalMetaShape(
    payload: Record<string, unknown>,
  ): payload is Record<string, unknown> & { entry: unknown[] } {
    return (
      payload.object === 'whatsapp_business_account' &&
      Array.isArray(payload.entry)
    );
  }
}
