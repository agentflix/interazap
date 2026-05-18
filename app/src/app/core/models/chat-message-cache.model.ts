import type { CalledMessage } from '@core/models/called-message.model';

export interface ChatMessageCacheDelegate {
  getMessages(ticketId: string): CalledMessage[];
  setMessages(ticketId: string, messages: CalledMessage[]): void;
}
