/**
 * Modelos de payload do controller de broadcast interno (domínio realtime).
 */

/** Payload de broadcast genérico recebido da API interna. */
export interface BroadcastEventDto {
  event: string;
  room?: string | null;
  data: Record<string, unknown>;
}

/** Payload para atualização de status de mensagem de chat. */
export interface MessageStatusDto {
  message_id: string;
  ticket_id: string;
  tenant_id: string;
  status: string;
  error_message?: string | null;
  sent_at?: string | null;
  delivered_at?: string | null;
  read_at?: string | null;
}

/** Payload para broadcast de nova mensagem de chat. */
export interface NewMessageDto {
  ticket_id: string;
  tenant_id: string;
  message: Record<string, unknown>;
}

/** Payload de evento relacionado a execução de run de IA. */
export interface AiRunEventDto {
  event: string;
  data: Record<string, unknown>;
}
