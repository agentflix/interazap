/**
 * Modelos de payload do controller interno de broadcast.
 */

/** Payload de broadcast genérico para room específica ou escopo global. */
export interface BroadcastEventDto {
  event: string;
  room?: string;
  data: Record<string, unknown>;
}

/**
 * Status aceitos para atualização de mensagens no canal interno.
 * Representa o ciclo de vida completo de uma mensagem de chat.
 */
export type MessageStatus =
  | 'pending'
  | 'sent'
  | 'delivered'
  | 'read'
  | 'failed';

/** Payload para evento de alteração de status de mensagem. */
export interface MessageStatusEventDto {
  message_id: string;
  ticket_id: string;
  tenant_id: string;
  status: MessageStatus;
  error_message?: string;
  sent_at?: string;
  delivered_at?: string;
  read_at?: string;
}

/** Estrutura de mensagem transmitida para UI no evento de nova mensagem. */
export interface InternalNewMessagePayload {
  id: string;
  content?: string;
  type?: string;
  direction?: string;
  status?: string;
  created_at?: string;
  [key: string]: unknown;
}

/** Payload para evento de nova mensagem de chat. */
export interface NewMessageEventDto {
  ticket_id: string;
  tenant_id: string;
  message: InternalNewMessagePayload;
}
