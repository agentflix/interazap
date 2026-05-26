import {
  Body,
  Controller,
  Get,
  HttpCode,
  Logger,
  Param,
  Post,
  UseGuards,
} from '@nestjs/common';
import { RedisService } from '../../infrastructure/redis/redis.service';
import { TelegramNormalizerService } from '../services/telegram-normalizer.service';
import { TelegramUpdateDto } from '../dto/telegram-update.dto';
import { WebhookHmacSignatureGuard } from '../guards/webhook-hmac-signature.guard';
import type { NormalizedTelegramEvent } from '../services/telegram-normalizer.service';

const TELEGRAM_INBOUND_STREAM = 'telegram.inbound';

/**
 * Recebe callbacks de webhook da Telegram Bot API.
 *
 * Contexto: módulo bot. Todo endpoint deve retornar 200 rapidamente —
 * o Telegram reenvia em caso de timeout. O processamento pesado é
 * delegado a um consumer do Redis Stream.
 */
@Controller('webhooks/telegram')
export class TelegramWebhookController {
  private readonly logger = new Logger(TelegramWebhookController.name);

  constructor(
    private readonly normalizer: TelegramNormalizerService,
    private readonly redisService: RedisService,
  ) {}

  /**
   * POST /webhooks/telegram/:token
   *
   * Recebe atualizações da Telegram Bot API.
   * Protegido pelo guard de assinatura HMAC (header X-Telegram-Bot-Api-Secret-Token).
   * @param token Token único do bot na URL do webhook
   * @param update Payload de atualização recebido do Telegram
   * @returns Confirmação `{ ok: true }`
   */
  @Post(':token')
  @UseGuards(WebhookHmacSignatureGuard)
  @HttpCode(200)
  async handleWebhook(
    @Param('token') token: string,
    @Body() update: TelegramUpdateDto,
  ): Promise<{ ok: true }> {
    try {
      this.logger.debug(
        `Received Telegram update_id=${update.update_id} for token=${token.substring(0, 8)}…`,
      );

      const normalized: NormalizedTelegramEvent | null =
        this.normalizer.normalize(token, update);

      if (!normalized) {
        this.logger.debug(
          `Unsupported update type for update_id=${update.update_id} — skipping`,
        );
        return { ok: true };
      }

      await this.publishToStream(normalized);

      this.logger.debug(
        `Published event ${normalized.idempotencyKey} to stream`,
      );
    } catch (error: unknown) {
      // Always return 200 to Telegram to prevent retries.
      // Log the error internally for debugging.
      const message = error instanceof Error ? error.message : String(error);
      this.logger.error(
        `Failed to process Telegram update_id=${update.update_id}: ${message}`,
        error instanceof Error ? error.stack : undefined,
      );
    }

    return { ok: true };
  }

  /**
   * GET /webhooks/telegram/health
   *
   * Endpoint de health check para monitoramento do serviço de webhook.
   * @returns Objeto `{ status: 'ok', service: 'telegram-webhook' }`
   */
  @Get('health')
  health(): { status: 'ok'; service: 'telegram-webhook' } {
    return { status: 'ok', service: 'telegram-webhook' };
  }

  // ─── Private ───────────────────────────────────────────────

  /**
   * Publica um evento Telegram normalizado no Redis Stream
   * para processamento assíncrono pelos consumers do backend.
   * @param event Evento normalizado a ser publicado
   */
  private async publishToStream(event: NormalizedTelegramEvent): Promise<void> {
    const streamPayload: Record<string, unknown> = {
      provider: event.provider,
      webhook_token: event.webhookToken,
      idempotency_key: event.idempotencyKey,
      direction: event.direction,
      event_type: event.eventType,
      chat_id: event.chatId,
      remote_jid: event.remoteJid,
      sender_name: event.senderName,
      sender_username: event.senderUsername ?? '',
      sender_phone: event.senderPhone ?? '',
      is_from_me: event.isFromMe,
      message_id: event.messageId,
      type: event.type,
      text: event.text ?? '',
      caption: event.caption ?? '',
      file_id: event.fileId ?? '',
      file_name: event.fileName ?? '',
      mime_type: event.mimeType ?? '',
      file_size: event.fileSize ?? 0,
      latitude: event.latitude ?? '',
      longitude: event.longitude ?? '',
      edited: event.edited ?? false,
      edit_date: event.editDate ?? '',
      timestamp: event.timestamp.toISOString(),
      raw_update_id: event.rawUpdateId,
      reply_to_message_id: event.replyToMessageId ?? '',
    };

    if (event.reactions?.length) {
      streamPayload.reactions = event.reactions;
    }

    await this.redisService.publishStream(
      TELEGRAM_INBOUND_STREAM,
      streamPayload,
    );
  }
}
