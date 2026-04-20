import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { LongPollingStrategy } from './long-polling.strategy';
import { WebhookStrategy } from './webhook.strategy';
import type { PollingStrategy } from './polling-strategy.interface';

type PollingMode = 'long_polling' | 'webhook';

/**
 * Factory that resolves the active Telegram update-reception strategy
 * based on the `TELEGRAM_POLLING_MODE` env variable.
 *
 * Defaults:
 * - production  → `webhook`
 * - development → `long_polling`
 *
 * Supports hot-swapping at runtime via `switchStrategy()`.
 */
@Injectable()
export class PollingStrategyFactory {
  private readonly logger = new Logger(PollingStrategyFactory.name);

  /** Override set at runtime by `switchStrategy()`. Takes precedence over env. */
  private runtimeMode: PollingMode | null = null;

  constructor(
    private readonly configService: ConfigService,
    private readonly longPolling: LongPollingStrategy,
    private readonly webhook: WebhookStrategy,
  ) {}

  // ─── Public API ────────────────────────────────────────────

  /**
   * Returns the strategy instance that matches the current mode.
   *
   * Resolution order:
   * 1. Runtime override (set by `switchStrategy`)
   * 2. `TELEGRAM_POLLING_MODE` env variable
   * 3. Environment-based default (production → webhook, else → long_polling)
   */
  getStrategy(): PollingStrategy {
    const mode = this.resolveMode();

    this.logger.debug(`Resolved polling mode: ${mode}`);

    if (mode === 'long_polling') {
      return this.longPolling;
    }

    return this.webhook;
  }

  /**
   * Hot-swap the active strategy at runtime.
   *
   * 1. Stops whichever strategy is currently active
   * 2. Persists the new mode for subsequent `getStrategy()` calls
   * 3. Starts the new strategy
   */
  async switchStrategy(
    botToken: string,
    webhookToken: string,
    newMode: PollingMode,
  ): Promise<void> {
    const currentStrategy = this.getActiveStrategy();

    this.logger.log(
      `Switching strategy: ${currentStrategy?.name ?? 'none'} → ${newMode}`,
    );

    // Stop whichever strategy is currently running
    if (currentStrategy?.isActive()) {
      await currentStrategy.stop();
    }

    this.runtimeMode = newMode;

    const nextStrategy = this.getStrategy();
    await nextStrategy.start(botToken, webhookToken);

    this.logger.log(`Strategy switched to ${nextStrategy.name} ✓`);
  }

  // ─── Private ───────────────────────────────────────────────

  private resolveMode(): PollingMode {
    if (this.runtimeMode) {
      return this.runtimeMode;
    }

    const envMode = this.configService.get<string>('TELEGRAM_POLLING_MODE');

    if (envMode === 'long_polling' || envMode === 'webhook') {
      return envMode;
    }

    const isProduction =
      this.configService.get<string>('NODE_ENV') === 'production';

    return isProduction ? 'webhook' : 'long_polling';
  }

  /**
   * Returns whichever strategy is currently active (running),
   * or `null` if neither is active.
   */
  private getActiveStrategy(): PollingStrategy | null {
    if (this.longPolling.isActive()) {
      return this.longPolling;
    }
    if (this.webhook.isActive()) {
      return this.webhook;
    }
    return null;
  }
}
