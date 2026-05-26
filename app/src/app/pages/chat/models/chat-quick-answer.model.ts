/** Resposta rápida disponível para o composer de mensagens. */
export interface ChatQuickAnswer {
  id: string;
  /** Atalho de texto (ex: `/oi`) para sugestão automática. */
  shortcut: string;
  /** Conteúdo completo da mensagem. */
  content: string;
  is_active: boolean;
}
