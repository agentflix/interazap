/** Evento de mensagem recebida via realtime, normalizado pelo listener. */
export interface IncomingMessageEvent {
  /** ID do ticket ao qual a mensagem pertence. */
  ticketId: string | null;
  /** ID do contato remetente. */
  contactId: string | null;
  /** Direção da mensagem: `incoming` ou `outgoing`. */
  direction: string | null;
}
