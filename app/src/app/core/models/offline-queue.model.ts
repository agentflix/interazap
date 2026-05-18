export interface OfflineQueuedMessage {
  id: string;
  calledId: string;
  content: string;
  type: 'text';
  clientMessageId: string;
  createdAt: string;
  attempts: number;
}
