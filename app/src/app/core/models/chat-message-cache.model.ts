import type { CalledMessage } from '@core/models/called-message.model';

/**
 * Interface de delegação para o cache de mensagens de chat por ticket.
 *
 * Contexto: abstrai a camada de cache de mensagens do serviço de chat,
 * permitindo que o cache seja armazenado em memória, IndexedDB ou outra estratégia.
 */
export interface ChatMessageCacheDelegate {
  /**
   * Retorna as mensagens em cache para o ticket informado.
   * @param ticketId ID do ticket
   * @returns Lista de mensagens em cache (vazia se não houver cache)
   */
  getMessages(ticketId: string): CalledMessage[];

  /**
   * Armazena ou atualiza as mensagens em cache para o ticket informado.
   * @param ticketId ID do ticket
   * @param messages Lista de mensagens a armazenar
   */
  setMessages(ticketId: string, messages: CalledMessage[]): void;
}
