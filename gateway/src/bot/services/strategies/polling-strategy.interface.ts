/**
 * Contrato para estratégias de recepção de atualizações do Telegram.
 *
 * Contexto: módulo bot. Duas implementações:
 * - LongPollingStrategy: dev/fallback — faz polling via getUpdates
 * - WebhookStrategy: produção — registra webhook HTTPS
 */
export interface PollingStrategy {
  /** Identificador da estratégia para logs e correspondência de configuração. */
  readonly name: string;

  /**
   * Ativa esta estratégia para um bot específico.
   * @param botToken Token da Telegram Bot API (ex: "123456:ABC-DEF...")
   * @param webhookToken Token único do bot utilizado no caminho da URL do webhook
   */
  start(botToken: string, webhookToken: string): Promise<void>;

  /** Encerra a estratégia graciosamente. */
  stop(): Promise<void>;

  /** Indica se a estratégia está ativa e processando atualizações. */
  isActive(): boolean;
}
