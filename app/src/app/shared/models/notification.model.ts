/**
 * Enum com os tipos de notificação disponíveis na API.
 */
export enum NotificationTypeEnum {
  NewTicket = 'new_ticket',
  TicketAssigned = 'ticket_assigned',
  TicketClosed = 'ticket_closed',
  System = 'system',
  Billing = 'billing',
}

/**
 * Entidade de notificação retornada pela API.
 */
export interface Notification {
  id: string;
  tenant_id: string;
  user_id: string;
  type: string;
  title: string;
  body: string | null;
  data?: Record<string, unknown>;
  channel?: string;
  status?: string;
  sent_at?: string | null;
  read_at?: string | null;
  created_at: string;
}

/**
 * Resposta da API para busca da lista de notificações.
 */
export interface NotificationListResponse {
  data: Notification[];
  unread_count: number;
}
