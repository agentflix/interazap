export interface ChatMessageStatusEvent {
  message_id?: string;
  external_id?: string;
  ticket_id?: string;
  status: 'pending' | 'sent' | 'delivered' | 'read' | 'failed' | 'deleted';
  error_message?: string;
  sent_at?: string;
  delivered_at?: string;
  read_at?: string;
  file_url?: string | null;
  file_name?: string | null;
  mime_type?: string | null;
  file_size?: number | null;
  media_transcription_status?: 'pending' | 'processing' | 'completed' | 'failed' | null;
  media_transcription?: string | null;
}

export interface ChatTypingEvent {
  ticket_id: string;
  user_id?: string;
  contact_id?: string;
  is_typing: boolean;
  presence?: 'composing' | 'recording' | 'paused';
}

export interface ChatNewMessageEvent {
  ticket_id: string;
  message: {
    id: string;
    content?: string;
    type?: string;
    direction?: string;
    status?: string;
    created_at?: string;
    [key: string]: unknown;
  };
}

export interface ChatMessageReactionEvent {
  message_id: string;
  ticket_id: string;
  tenant_id?: string;
  emoji: string | null;
  from_me?: boolean;
  reactions?: { emoji: string; from_me: boolean; timestamp: string }[];
}

export interface ChatMessageEditEvent {
  message_id: string;
  ticket_id: string;
  tenant_id?: string;
  external_id?: string;
  content: string;
  is_edited: boolean;
  edited_at?: string | null;
}

export interface ChatMessageDeleteEvent {
  message_id: string;
  ticket_id: string;
  tenant_id?: string;
  status?: 'deleted';
  deleted_at?: string | null;
  deleted_by?: string | null;
}

export type ChatActivitySubeventType =
  | 'msg.received'
  | 'msg.status'
  | 'msg.reaction'
  | 'msg.edit'
  | 'msg.delete'
  | 'ai.processing.started'
  | 'ai.processing.completed'
  | 'ai.processing.failed'
  | 'ai.processing.rejected'
  | 'ai.run.streaming'
  | 'chat.list.updated'
  | 'contact.updated'
  | 'deal.updated'
  | 'negotiation.status.changed'
  | 'ticket.new'
  | 'ticket.updated';

export interface ChatActivitySubevent {
  type: ChatActivitySubeventType;
  data: unknown;
}

export interface ChatActivityEvent {
  event: 'chat.activity';
  chatId?: string;
  ticketId?: string;
  tenantId?: string;
  timestamp: string;
  subevents: ChatActivitySubevent[];
}

export interface ChatNewTicketEvent {
  ticket_id: string;
  tenant_id?: string;
  ticket: {
    id: string;
    protocol?: string;
    contact_id?: string;
    remote_jid?: string;
    push_name?: string;
    status?: string;
    [key: string]: unknown;
  };
}

export interface ChatTicketUpdatedEvent {
  ticket_id: string;
  tenant_id?: string;
  ticket: {
    id: string;
    protocol?: string;
    last_message_at?: string;
    latest_message?: { content?: string; [key: string]: unknown };
    [key: string]: unknown;
  };
}

export interface ChatTicketSentimentUpdatedEvent {
  ticket_id: string;
  tenant_id?: string;
  sentiment: 'positive' | 'neutral' | 'negative' | 'critical';
  sentiment_score: number;
}

export interface IntegrationConnectionEvent {
  tenant_id?: string | null;
  instance_id?: string | null;
  token?: string | null;
  status?: string | null;
  connected?: boolean;
  qrcode?: string | null;
  paircode?: string | null;
  raw?: unknown;
}

export type ChatRealtimeEvent =
  | { type: 'status'; payload: ChatMessageStatusEvent }
  | { type: 'new'; payload: ChatNewMessageEvent }
  | { type: 'typing'; payload: ChatTypingEvent }
  | { type: 'reaction'; payload: ChatMessageReactionEvent }
  | { type: 'edit'; payload: ChatMessageEditEvent }
  | { type: 'delete'; payload: ChatMessageDeleteEvent }
  | { type: 'newTicket'; payload: ChatNewTicketEvent };
