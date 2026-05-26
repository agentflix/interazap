import { Injectable, Logger } from '@nestjs/common';
import { EventEmitter } from 'node:events';
import { TelegramClientService } from '../telegram-client.service';
import type { PollingStrategy } from './polling-strategy.interface';

const POLL_TIMEOUT_SECONDS = 55;
const RETRY_DELAY_MS = 5_000;
const ALLOWED_UPDATES = ['message', 'edited_message', 'message_reaction'];

/**
 * Estratégia de long-polling para receber atualizações do Telegram.
 *
 * Contexto: módulo bot. Destinada a ambientes de desenvolvimento ou como
 * fallback de produção quando o webhook não estiver acessível. Usa o endpoint
 * de long-polling do Telegram com timeout de 55s (abaixo do limite de 60s).
 *
 * Emite eventos `telegram.update` para cada atualização recebida.
 */
@Injectable()
export class LongPollingStrategy implements PollingStrategy {
  readonly name = 'long_polling';

  private isRunning = false;
  private abortController: AbortController | null = null;
  private offset = 0;

  /** EventEmitter Node.js utilizado para propagar as atualizações recebidas. */
  readonly events = new EventEmitter();

  private readonly logger = new Logger(LongPollingStrategy.name);

  constructor(private readonly telegramClient: TelegramClientService) {}

  // ─── Public API ────────────────────────────────────────────

  /**
   * Inicia o loop de long-polling para o bot informado.
   * Remove o webhook existente antes de iniciar o polling.
   * @param botToken Token da Telegram Bot API
   * @param webhookToken Token único do bot no URL de webhook (não usado no polling)
   */
  async start(botToken: string, webhookToken: string): Promise<void> {
    if (this.isRunning) {
      this.logger.warn(
        'Long polling already running — ignoring duplicate start',
      );
      return;
    }

    this.logger.log('Starting long-polling strategy…');

    // Remove any existing webhook so Telegram allows getUpdates
    await this.telegramClient.deleteWebhook(botToken, false);
    this.logger.debug('Existing webhook deleted (if any)');

    this.isRunning = true;
    this.offset = 0;

    // Fire-and-forget — the loop is self-sustaining
    this.pollLoop(botToken, webhookToken).catch((error: unknown) => {
      this.logger.error(
        `Poll loop terminated unexpectedly: ${error instanceof Error ? error.message : String(error)}`,
      );
      this.isRunning = false;
    });
  }

  /**
   * Interrompe o loop de long-polling graciosamente, abortando a requisição em curso.
   */
  async stop(): Promise<void> {
    if (!this.isRunning) {
      return;
    }

    this.logger.log('Stopping long-polling strategy…');
    this.isRunning = false;

    if (this.abortController) {
      this.abortController.abort();
      this.abortController = null;
    }
  }

  /** Retorna true se o loop de polling estiver em execução. */
  isActive(): boolean {
    return this.isRunning;
  }

  // ─── Private ───────────────────────────────────────────────

  /**
   * Loop principal de polling que chama getUpdates repetidamente.
   * Avança o offset para evitar reprocessamento e emite eventos para cada atualização.
   * @param botToken Token da Telegram Bot API
   * @param _webhookToken Não utilizado no polling (mantido para compatibilidade da interface)
   */
  private async pollLoop(
    botToken: string,
    _webhookToken: string,
  ): Promise<void> {
    while (this.isRunning) {
      try {
        this.abortController = new AbortController();

        const response = await this.telegramClient.getUpdates(
          botToken,
          this.offset > 0 ? this.offset : undefined,
          POLL_TIMEOUT_SECONDS,
          ALLOWED_UPDATES,
        );

        // AbortController may have been triggered while awaiting
        if (!this.isRunning) {
          break;
        }

        if (response.ok && response.result && response.result.length > 0) {
          const updates = response.result;

          this.logger.debug(`Received ${updates.length} update(s)`);

          for (const update of updates) {
            this.events.emit('telegram.update', update);
          }

          // Advance offset past the highest update_id to avoid reprocessing
          const maxId = updates.reduce(
            (max: number, u: { update_id: number }) =>
              u.update_id > max ? u.update_id : max,
            0,
          );
          this.offset = maxId + 1;
        }
      } catch (error: unknown) {
        if (!this.isRunning) {
          // Abort-triggered error during shutdown — expected
          break;
        }

        const message = error instanceof Error ? error.message : String(error);
        this.logger.error(
          `Polling error: ${message} — retrying in ${RETRY_DELAY_MS / 1_000}s`,
        );

        await this.delay(RETRY_DELAY_MS);
      }
    }

    this.logger.log('Long-polling loop exited');
  }

  /**
   * Aguarda o tempo especificado em milissegundos.
   * @param ms Tempo de espera em milissegundos
   */
  private delay(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }
}
