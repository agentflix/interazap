import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { createHmac, randomBytes } from 'node:crypto';
import { TelegramClientService } from '../telegram-client.service';
import type { PollingStrategy } from './polling-strategy.interface';

/**
 * Estratégia de webhook para receber atualizações do Telegram em produção.
 *
 * Contexto: módulo bot. Registra um webhook HTTPS em `start()` e valida
 * via `getWebhookInfo`. Usa um segredo HMAC para que o gateway possa
 * verificar que as requisições recebidas são genuinamente do Telegram.
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

  /**
   * Registra o webhook no Telegram e valida a URL registrada.
   * @param botToken Token da Telegram Bot API
   * @param webhookToken Token único do bot utilizado no caminho da URL do webhook
   * @throws Error se GATEWAY_BASE_URL não estiver configurada, se o registro falhar
   *   ou se a URL verificada não coincidir
   */
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

  /**
   * Remove o webhook do Telegram e limpa o estado interno.
   */
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

  /** Retorna true se o webhook estiver registrado e ativo. */
  isActive(): boolean {
    return this.active;
  }

  /**
   * Retorna o segredo HMAC atual para que o controller do webhook possa
   * verificar o header `X-Telegram-Bot-Api-Secret-Token`.
   * @returns Segredo HMAC ou null se a estratégia não estiver ativa
   */
  getSecretToken(): string | null {
    return this.secretToken;
  }

  // ─── Private ───────────────────────────────────────────────

  /**
   * Gera um segredo HMAC-SHA256 determinístico para este bot.
   * Se TELEGRAM_WEBHOOK_SECRET estiver configurado, usa-o como chave;
   * caso contrário, gera uma string hex aleatória de 32 bytes.
   * @param botToken Token do bot usado como dados do HMAC
   * @returns Segredo como string hex
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
   * Em produção (`NODE_ENV=production`), webhooks devem obrigatoriamente usar HTTPS.
   * Em outros ambientes, HTTP é permitido para túneis locais.
   * @param baseUrl URL base do gateway a ser validada
   * @throws Error se o ambiente for produção e a URL não usar HTTPS
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
