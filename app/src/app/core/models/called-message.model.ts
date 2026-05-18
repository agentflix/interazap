export interface CalledMessageUser {
  id: string | number;
  name: string;
}

export interface CalledMessageContactPhone {
  number?: string;
  type?: string;
}

export interface CalledMessageContactEntry {
  fullName?: string;
  phoneNumber?: string;
  organization?: string;
  email?: string;
  phones?: CalledMessageContactPhone[];
}

export interface CalledMessageContactMetadata {
  fullName?: string;
  phoneNumber?: string;
  organization?: string;
  email?: string;
  url?: string;
  contacts?: CalledMessageContactEntry[];
}

export interface CalledMessageLocationMetadata {
  latitude?: number;
  longitude?: number;
  lat?: number;
  lng?: number;
  address?: string;
  name?: string;
}

export interface CalledMessageMetadata {
  contact?: CalledMessageContactMetadata;
  location?: CalledMessageLocationMetadata;
  [key: string]: unknown;
}

export interface CalledMessageQuotedMessage {
  id?: string | number | null;
  external_id?: string | null;
  content?: string | null;
  type?: string | null;
  direction?: 'incoming' | 'outgoing' | null;
  sender_name?: string | null;
  file_name?: string | null;
  mime_type?: string | null;
  is_edited?: boolean;
  edited_at?: string | null;
}

export interface CalledMessage {
  id: string | number;
  called_id?: string | number;
  ticket_id?: string | number;
  user_id?: string | number | null;
  contact_id?: string | number | null;
  external_id?: string | null;
  content?: string | null;
  type?: string;
  direction?: 'incoming' | 'outgoing';
  status?: string | null;
  is_deleted?: boolean;
  file_url?: string | null;
  file_name?: string | null;
  mime_type?: string | null;
  file_size?: number | null;
  sent_at?: string | null;
  delivered_at?: string | null;
  read_at?: string | null;
  deleted_at?: string | null;
  deleted_by?: string | null;
  reactions?: { emoji: string; from_me: boolean; timestamp: string }[] | null;
  is_edited?: boolean;
  edited_at?: string | null;
  edit_history?: { content: string; edited_at: string }[] | null;
  media_transcription?: string | null;
  media_transcription_status?: 'pending' | 'processing' | 'completed' | 'failed' | null;
  media_transcription_provider?: string | null;
  created_at?: string | null;
  metadata?: CalledMessageMetadata | null;
  quoted_message?: CalledMessageQuotedMessage | null;
  user?: CalledMessageUser | null;
}

export interface CalledMessageListResponse {
  data: {
    messages: CalledMessage[];
    meta: PaginationMeta;
  };
}

export interface CalledMessageSendResponse {
  data: CalledMessage;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  has_more: boolean;
}

export interface CalledMessageListOptions {
  page?: number;
  limit?: number;
  since?: string;
}

export interface CalledMessageMediaResponse {
  data: {
    url: string;
    file_name?: string | null;
    mime_type?: string | null;
    file_size?: number | null;
    size?: number | null;
    metadata?: Record<string, unknown> | null;
  };
}
