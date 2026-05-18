import type { CalledMessage } from '@core/models/called-message.model';

export interface MessageSendResultSent {
  status: 'sent';
  clientMessageId: string;
  message: CalledMessage;
}

export interface MessageSendResultQueued {
  status: 'queued';
  clientMessageId: string;
  queueId: string;
}

export type MessageSendResult = MessageSendResultSent | MessageSendResultQueued;

export interface OfflineQueueDeliveredEvent {
  queueId: string;
  calledId: string;
  clientMessageId: string;
  message: CalledMessage;
}

export interface OfflineQueueFailedEvent {
  queueId: string;
  calledId: string;
  clientMessageId: string;
}
