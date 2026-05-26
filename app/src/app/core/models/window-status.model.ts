/**
 * Representa o status da janela de 24 horas de mensagens de um contato.
 *
 * Usado para determinar se o contato pode receber mensagens de texto livre
 * ou se requer mensagens via template pela API do WhatsApp Business da Meta.
 *
 * @example
 * ```typescript
 * const status: WindowStatus = {
 *   canSendFreeText: true,
 *   lastMessageAt: new Date('2026-04-11T10:00:00Z')
 * };
 * ```
 */
export interface WindowStatus {
  /** Indica se o contato pode receber mensagens de texto livre (dentro da janela de 24h). */
  canSendFreeText: boolean;
  /** Timestamp da última mensagem recebida do contato, ou null se não houver mensagens. */
  lastMessageAt: Date | null;
}

/**
 * Envelope de resposta da API para o status de janela de mensagens.
 */
export interface WindowStatusResponse {
  data: WindowStatus;
}
