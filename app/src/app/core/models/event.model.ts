import type { PaginationMeta } from '@core/models/pagination.model';

export type EventStatus = 'scheduled' | 'completed' | 'cancelled';

export type EventType = 'meeting' | 'call' | 'task' | 'deadline' | 'reminder' | 'other';

export type EventRecurrence = 'none' | 'daily' | 'weekly' | 'monthly' | 'yearly';

export interface Event {
  id: string;
  tenant_id: string;
  user_id?: string | null;
  title: string;
  description?: string | null;
  location?: string | null;
  starts_at: string;
  ends_at?: string | null;
  is_all_day: boolean;
  status: EventStatus;
  type: EventType;
  recurrence?: string;
  recurrence_ends_at?: string | null;
  color?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface EventFilters {
  search?: string;
  start_date?: string;
  end_date?: string;
  user_id?: string;
  participant_id?: string;
  linkable_type?: 'contact' | 'company' | 'deal' | 'negotiation';
  linkable_id?: string;
  is_all_day?: boolean;
  recurrence?: EventRecurrence;
  location?: string;
  has_reminders?: boolean;
  status?: EventStatus;
  type?: EventType;
  sort_by?: string;
  sort_dir?: 'asc' | 'desc';
  per_page?: number;
  page?: number;
}

export interface EventListResponse {
  success: boolean;
  data: Event[];
  meta?: PaginationMeta;
}
