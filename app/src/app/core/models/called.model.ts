export type CalledStatus = 'open' | 'pending' | 'in_progress' | 'closed';

export type CalledChannel = 'whatsapp' | 'internal' | 'external' | 'portal';

export type CalledSentiment = 'positive' | 'neutral' | 'negative' | 'critical';

export type CalledSortBy = 'last_message_at' | 'sentiment_score';

export interface CalledUserSummary {
  id: string | number;
  name: string;
  email?: string;
  department?: {
    id: string | number;
    name: string;
  } | null;
}

export interface CalledContactSummary {
  id: string | number;
  name: string;
  email?: string | null;
  phone?: string | null;
  whatsapp?: string | null;
  profile_picture_url?: string | null;
}

export interface CalledMessageSummary {
  id: string | number;
  content?: string | null;
  type?: string;
  created_at?: string | null;
}

export interface CalledEvaluationSummary {
  has_evaluation: boolean;
  rating?: number | null;
  comment?: string | null;
  submitted_at?: string | null;
}

export interface Called {
  id: string | number;
  company_id: string | number;
  user_id?: string | number | null;
  contact_id?: string | number | null;
  instance_id?: string | number | null;
  protocol?: string | null;
  profile_picture_url?: string | null;
  status: CalledStatus;
  is_bot_active?: boolean | null;
  current_ai_agent_id?: string | null;
  channel: CalledChannel;
  subject?: string | null;
  notes?: string | null;
  queued_at?: string | null;
  started_at?: string | null;
  closed_at?: string | null;
  closed_by?: string | null;
  close_reason?: string | null;
  closed_mode?: 'normal' | 'forced' | null;
  wait_duration_seconds?: number | null;
  service_duration_seconds?: number | null;
  created_at?: string | null;
  updated_at?: string | null;
  user?: CalledUserSummary | null;
  assigned_user?: CalledUserSummary | null;
  contact?: CalledContactSummary | null;
  last_message?: CalledMessageSummary | null;
  evaluation?: CalledEvaluationSummary | null;
  unread_count?: number;
  sentiment?: CalledSentiment | null;
  sentiment_score?: number | null;
  sentiment_updated_at?: string | null;
}

export interface CalledListFilters {
  search?: string;
  status?: CalledStatus;
  channel?: CalledChannel;
  contact_id?: string | number;
  instance_id?: string | number;
  user_id?: string | number;
  agent_id?: string | number;
  from?: string;
  to?: string;
  per_page?: number;
  page?: number;
  sentiment?: CalledSentiment;
  sort_by?: CalledSortBy;
  group_by_contact?: boolean;
}

export interface CalledListResponse {
  data: Called[];
  meta: {
    current_page: number;
    total: number;
    per_page: number;
    last_page: number;
  };
  counts?: CalledCounts;
}

export interface CalledCounts {
  all: number;
  pending: number;
  open: number;
  closed: number;
  in_progress?: number;
}

export interface CalledCreatePayload {
  contact_id: string | number;
  instance_id?: string | number;
  channel?: CalledChannel;
  subject?: string;
  notes?: string;
}

export interface CalledGetOptions {
  includeMessages?: boolean;
  messagesPerPage?: number;
}

export interface CalledResponse {
  data: {
    called: Called;
  };
}

export interface ChatTicketTransfer {
  id: string | number;
  ticket_id: string | number;
  from_user_id: string | number | null;
  to_user_id: string | number;
  reason: string;
  status: string;
  transferred_at?: string | null;
}

export interface CalledClosePayload {
  mode?: 'normal' | 'forced';
  reason?: string;
}

export interface CalledMessage {
  id: string | number;
  ticket_id: string | number;
  content?: string | null;
  type?: string;
  direction: 'incoming' | 'outgoing';
  status?: string;
  created_at?: string | null;
  user?: CalledUserSummary | null;
}

export interface CalledMessagesResponse {
  data: CalledMessage[];
  meta: {
    current_page: number;
    total: number;
    per_page: number;
    last_page: number;
  };
}
