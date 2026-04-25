import { Injectable, Logger } from '@nestjs/common';
import { EventEmitter } from 'node:events';
import { TelegramClientService } from '../telegram-client.service';
import type { PollingStrategy } from './polling-strategy.interface';

const POLL_TIMEOUT_SECONDS = 55;
const RETRY_DELAY_MS = 5_000;
const ALLOWED_UPDATES = ['message', 'edited_message', 'message_reaction'];

/**
 * Long-polling strategy for receiving Telegram updates.
 *
 * Intended for development environments or as a production fallback
 * when the webhook cannot be reached. Uses Telegram's long-polling
 * endpoint with a 55 s keep-alive timeout (< 60 s limit).
 *
 * Emits `telegram.update` events for each individual update received.
 */
@Injectable()
export class LongPollingStrategy implements PollingStrategy {
  readonly name = 'long_polling';

  private isRunning = false;
  private abortController: AbortController | null = null;
  private offset = 0;

  /** Node EventEmitter used to broadcast received updates. */
  readonly events = new EventEmitter();

  private readonly logger = new Logger(LongPollingStrategy.name);

  constructor(private readonly telegramClient: TelegramClientService) {}

  // ─── Public API ────────────────────────────────────────────

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

  isActive(): boolean {
    return this.isRunning;
  }

  // ─── Private ───────────────────────────────────────────────

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

  private delay(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }
}
