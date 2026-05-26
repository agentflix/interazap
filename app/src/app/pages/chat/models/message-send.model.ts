import type { CalledMessage } from '@core/models/called-message.model';

/** Resultado de envio de mensagem quando entregue com sucesso. */
export interface MessageSendResultSent {
  status: 'sent';
  /** ID temporário do cliente para rastrear a mensagem otimista. */
  clientMessageId: string;
  /** Mensagem confirmada pelo servidor. */
  message: CalledMessage;
}

/** Resultado de envio de mensagem quando enfileirada (offline queue). */
export interface MessageSendResultQueued {
  status: 'queued';
  /** ID temporário do cliente. */
  clientMessageId: string;
  /** ID da entrada na fila offline. */
  queueId: string;
}

/** Union type do resultado de envio de mensagem. */
export type MessageSendResult = MessageSendResultSent | MessageSendResultQueued;

/** Evento emitido quando uma mensagem enfileirada offline é entregue com sucesso. */
export interface OfflineQueueDeliveredEvent {
  queueId: string;
  calledId: string;
  clientMessageId: string;
  message: CalledMessage;
}

/** Evento emitido quando o envio de uma mensagem enfileirada offline falha definitivamente. */
export interface OfflineQueueFailedEvent {
  queueId: string;
  calledId: string;
  clientMessageId: string;
}
