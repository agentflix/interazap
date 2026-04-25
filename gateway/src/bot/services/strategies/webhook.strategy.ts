import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { createHmac, randomBytes } from 'node:crypto';
import { TelegramClientService } from '../telegram-client.service';
import type { PollingStrategy } from './polling-strategy.interface';

/**
 * Webhook strategy for receiving Telegram updates in production.
 *
 * Registers an HTTPS webhook on `start()` and validates it via
 * `getWebhookInfo`. Uses an HMAC secret so the gateway can verify
 * that incoming requests truly originate from Telegram.
 */
@Injectable()
export class WebhookStrategy implements PollingStrategy {
  readonly name = 'webhook';

  private active = false;
  private currentBotToken: string | null = null;
  private secretToken: string | null = null;

  private readonly logger = new Logger(WebhookStrategy.name);

  constructor(
    private readonly telegramClient: TelegramClientService,
    private readonly configService: ConfigService,
  ) {}

  // ─── Public API ────────────────────────────────────────────

  async start(botToken: string, webhookToken: string): Promise<void> {
    if (this.active) {
      this.logger.warn(
        'Webhook strategy already active — ignoring duplicate start',
      );
      return;
    }

    const baseUrl = this.configService.get<string>('GATEWAY_BASE_URL');
    if (!baseUrl) {
      throw new Error(
        'GATEWAY_BASE_URL env variable is required for webhook strategy',
      );
    }

    this.validateHttps(baseUrl);

    const webhookUrl = `${baseUrl}/webhooks/telegram/${webhookToken}`;
    this.secretToken = this.generateSecretToken(botToken);

    this.logger.log(`Registering webhook → ${webhookUrl}`);

    const setResult = await this.telegramClient.setWebhook(
      botToken,
      webhookUrl,
      this.secretToken,
    );

    if (!setResult.ok) {
      throw new Error(
        `setWebhook failed: ${setResult.description ?? 'unknown error'}`,
      );
    }

    // Verify that Telegram accepted the URL
    const info = await this.telegramClient.getWebhookInfo(botToken);
    if (!info.ok || !info.result?.url) {
      throw new Error(
        `getWebhookInfo verification failed: ${info.description ?? 'no URL returned'}`,
      );
    }

    const registeredUrl: string = info.result.url;
    if (registeredUrl !== webhookUrl) {
      throw new Error(
        `Webhook URL mismatch: expected "${webhookUrl}", got "${registeredUrl}"`,
      );
    }

    this.currentBotToken = botToken;
    this.active = true;

    this.logger.log('Webhook strategy active ✓');
  }

  async stop(): Promise<void> {
    if (!this.active) {
      return;
    }

    this.logger.log('Stopping webhook strategy…');

    if (this.currentBotToken) {
      try {
        await this.telegramClient.deleteWebhook(this.currentBotToken, false);
        this.logger.debug('Webhook deleted');
      } catch (error: unknown) {
        const msg = error instanceof Error ? error.message : String(error);
        this.logger.warn(`Failed to delete webhook on stop: ${msg}`);
      }
    }

    this.active = false;
    this.currentBotToken = null;
    this.secretToken = null;
  }

  isActive(): boolean {
    return this.active;
  }

  /**
   * Returns the current HMAC secret so the webhook controller can
   * verify the `X-Telegram-Bot-Api-Secret-Token` header.
   */
  getSecretToken(): string | null {
    return this.secretToken;
  }

  // ─── Private ───────────────────────────────────────────────

  /**
   * Generates a deterministic HMAC-SHA256 secret for this bot.
   * If TELEGRAM_WEBHOOK_SECRET is set, uses it as the key;
   * otherwise generates a random 32-byte hex string.
   */
  private generateSecretToken(botToken: string): string {
    const configSecret = this.configService.get<string>(
      'TELEGRAM_WEBHOOK_SECRET',
    );

    if (configSecret) {
      return createHmac('sha256', configSecret).update(botToken).digest('hex');
    }

    return randomBytes(32).toString('hex');
  }

  /**
   * In production (`NODE_ENV=production`), webhooks MUST use HTTPS.
   * In other environments we allow HTTP for local tunnels etc.
   */
  private validateHttps(baseUrl: string): void {
    const isProduction =
      this.configService.get<string>('NODE_ENV') === 'production';

    if (isProduction && !baseUrl.startsWith('https://')) {
      throw new Error(
        'Webhook URL must use HTTPS in production. ' + `Got: ${baseUrl}`,
      );
    }
  }
}
