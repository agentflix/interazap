/**
 * Envelope padrão de resposta da Telegram Bot API.
 *
 * Contexto: módulo bot. Encapsula o resultado de qualquer chamada à API,
 * incluindo campos de erro quando `ok` é `false`.
 * @see https://core.telegram.org/bots/api#making-requests
 */
export interface TgResult<T> {
  ok: boolean;
  result?: T;
  error_code?: number;
  description?: string;
  parameters?: { retry_after?: number; migrate_to_chat_id?: number };
}
