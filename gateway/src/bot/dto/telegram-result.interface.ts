/**
 * Telegram Bot API standard response envelope.
 * @see https://core.telegram.org/bots/api#making-requests
 */
export interface TgResult<T> {
  ok: boolean;
  result?: T;
  error_code?: number;
  description?: string;
  parameters?: { retry_after?: number; migrate_to_chat_id?: number };
}
