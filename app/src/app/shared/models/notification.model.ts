/**
 * Enum representing the available notification types from the API.
 */
export enum NotificationTypeEnum {
  NewTicket = 'new_ticket',
  TicketAssigned = 'ticket_assigned',
  TicketClosed = 'ticket_closed',
  System = 'system',
  Billing = 'billing',
}

/**
 * Notification entity returned by the API.
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
 * API response for fetching the notification list.
 */
export interface NotificationListResponse {
  data: Notification[];
  unread_count: number;
}
