/**
 * Contract for Telegram update reception strategies.
 *
 * Two implementations:
 * - LongPollingStrategy: dev/fallback — polls getUpdates
 * - WebhookStrategy: production — registers HTTPS webhook
 */
export interface PollingStrategy {
  /** Strategy identifier for logging and config matching. */
  readonly name: string;

  /**
   * Activate this strategy for a given bot.
   * @param botToken  Telegram Bot API token (e.g. "123456:ABC-DEF...")
   * @param webhookToken  Unique per-bot token used in the webhook URL path
   */
  start(botToken: string, webhookToken: string): Promise<void>;

  /** Gracefully shut down this strategy. */
  stop(): Promise<void>;

  /** Whether the strategy is currently active and processing updates. */
  isActive(): boolean;
}
