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
  HttpCode,
  HttpStatus,
  type RawBodyRequest,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import type { Request } from 'express';
import * as crypto from 'crypto';
import { ChatWebhookService } from '../services/chat-webhook.service';
import { MetaAdapter } from '../providers/meta/meta.adapter';

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
    private readonly metaAdapter: MetaAdapter,
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
   * Sempre responde `200 OK` após a assinatura ser validada — mesmo quando o
   * `phone_number_id` é desconhecido, o payload tem forma inesperada, ou não
   * produz eventos resolvíveis. A Meta reentrega em loop diante de qualquer
   * 4xx/5xx, então qualquer problema de roteamento/forma é só logado.
   */
  @Post()
  @HttpCode(HttpStatus.OK)
  async handleWebhook(
    @Headers('x-hub-signature-256') signature: string,
    @Body() rawPayload: Record<string, unknown>,
    @Req() req: RawBodyRequest<Request>,
  ): Promise<{ success: boolean }> {
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

    // 6. Adapter resolve tenant/instancia por phone_number_id/waba_id e
    // normaliza o lote completo (entry[] -> changes[] -> messages[]|statuses[]).
    // Instancia desconhecida ou payload sem eventos resolviveis apenas gera
    // warning — nunca um erro 4xx que provocaria reentrega em loop da Meta.
    try {
      const events = await this.metaAdapter.normalizeWebhookBatch(rawPayload);

      if (events.length === 0) {
        this.logger.warn(
          'Meta webhook did not produce any resolvable event (unknown phone_number_id/waba_id or empty payload)',
        );
        return { success: true };
      }

      // Cada evento e processado de forma independente — falha em um item
      // nao aborta o processamento dos demais (ver ChatWebhookService.handleNormalizedEvents).
      await this.chatWebhookService.handleNormalizedEvents(events);
    } catch (error) {
      this.logger.error(
        `Failed to process Meta webhook payload: ${error instanceof Error ? error.message : String(error)}`,
        error instanceof Error ? error.stack : undefined,
      );
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
