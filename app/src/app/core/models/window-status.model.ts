/**
 * Representa o status da janela de atendimento Meta (24h padrão ou 72h CTWA) de um contato.
 *
 * Usado para determinar se o contato pode receber mensagens de texto livre
 * ou se requer mensagens via template pela API do WhatsApp Business da Meta.
 *
 * @remarks
 * `expiresAt` é a fonte autoritativa, calculada e persistida pelo backend
 * (`meta_window_expires_at` em `chat_tickets`). Quando `expiresAt` é `null` ou está
 * no passado, o front deve cair no fallback por `lastMessageAt + 24h` — defesa em
 * profundidade contra um campo persistido ausente/desatualizado.
 *
 * @example
 * ```typescript
 * const status: WindowStatus = {
 *   canSendFreeText: true,
 *   lastMessageAt: new Date('2026-04-11T10:00:00Z'),
 *   expiresAt: new Date('2026-04-12T10:00:00Z'),
 *   windowType: '24h',
 * };
 * ```
 */
export interface WindowStatus {
  /** Indica se o contato pode receber mensagens de texto livre (dentro da janela aberta). */
  canSendFreeText: boolean;
  /** Timestamp da última mensagem recebida do contato, ou null se não houver mensagens. */
  lastMessageAt: Date | null;
  /** Expiração autoritativa da janela persistida pelo backend, ou null se ausente. */
  expiresAt: Date | null;
  /** Tipo de janela: `'24h'` padrão ou `'72h'` (CTWA — anúncio clique-para-WhatsApp), ou null. */
  windowType: '24h' | '72h' | null;
}

/**
 * Formato bruto do payload retornado pela API — datas chegam como string ISO 8601.
 * O `WindowVerificationService` converte para `Date` ao mapear para `WindowStatus`.
 */
export interface WindowStatusApiPayload {
  canSendFreeText: boolean;
  lastMessageAt: string | null;
  expiresAt: string | null;
  windowType: '24h' | '72h' | null;
}

/**
 * Envelope de resposta da API para o status de janela de mensagens.
 */
export interface WindowStatusResponse {
  data: WindowStatusApiPayload;
}
