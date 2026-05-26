/**
 * Representa uma mensagem enfileirada para envio posterior quando offline.
 *
 * Contexto: usado pelo serviço de fila offline para persistir mensagens
 * que não puderam ser enviadas por falta de conectividade. As mensagens
 * são reprocessadas automaticamente quando a conexão é restaurada.
 */
export interface OfflineQueuedMessage {
  /** Identificador único da mensagem na fila offline. */
  id: string;
  /** ID do ticket (chamado) ao qual a mensagem pertence. */
  calledId: string;
  /** Conteúdo textual da mensagem. */
  content: string;
  /** Tipo da mensagem (atualmente apenas 'text'). */
  type: 'text';
  /** ID temporário gerado pelo cliente para identificação otimista. */
  clientMessageId: string;
  /** Data e hora em que a mensagem foi enfileirada (ISO 8601). */
  createdAt: string;
  /** Número de tentativas de envio realizadas. */
  attempts: number;
}
