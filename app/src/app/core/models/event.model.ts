import type { PaginationMeta } from '@core/models/pagination.model';

/**
 * Status possíveis de um evento da agenda.
 */
export type EventStatus = 'scheduled' | 'completed' | 'cancelled';

/**
 * Tipos de evento suportados pela agenda do CRM.
 *
 * - `meeting`: reunião
 * - `call`: ligação
 * - `task`: tarefa
 * - `deadline`: prazo
 * - `reminder`: lembrete
 * - `other`: outro
 */
export type EventType = 'meeting' | 'call' | 'task' | 'deadline' | 'reminder' | 'other';

/**
 * Frequência de recorrência de um evento da agenda.
 */
export type EventRecurrence = 'none' | 'daily' | 'weekly' | 'monthly' | 'yearly';

/**
 * Representa um evento da agenda do CRM.
 *
 * Contexto: usado no módulo de Agenda (crm/agenda) para gerenciar
 * compromissos vinculados a negociações, contatos e empresas.
 */
export interface Event {
  id: string;
  tenant_id: string;
  /** ID do usuário responsável pelo evento. */
  user_id?: string | null;
  title: string;
  description?: string | null;
  /** Local onde o evento ocorrerá. */
  location?: string | null;
  /** Data e hora de início do evento (ISO 8601). */
  starts_at: string;
  /** Data e hora de término do evento (ISO 8601). */
  ends_at?: string | null;
  /** Indica se o evento ocupa o dia inteiro (sem horário específico). */
  is_all_day: boolean;
  status: EventStatus;
  type: EventType;
  recurrence?: string;
  /** Data de término da recorrência. */
  recurrence_ends_at?: string | null;
  /** Cor do evento no calendário (hex ou nome). */
  color?: string | null;
  created_at?: string;
  updated_at?: string;
}

/**
 * Filtros para listagem de eventos da agenda.
 *
 * Contexto: parâmetros aceitos pelo endpoint de listagem de eventos do CRM.
 */
export interface EventFilters {
  search?: string;
  start_date?: string;
  end_date?: string;
  user_id?: string;
  participant_id?: string;
  /** Tipo do vínculo do evento (contato, empresa, negociação, etc.). */
  linkable_type?: 'contact' | 'company' | 'deal' | 'negotiation';
  /** ID do registro vinculado ao evento. */
  linkable_id?: string;
  is_all_day?: boolean;
  recurrence?: EventRecurrence;
  location?: string;
  /** Filtra eventos que possuem lembretes configurados. */
  has_reminders?: boolean;
  status?: EventStatus;
  type?: EventType;
  sort_by?: string;
  sort_dir?: 'asc' | 'desc';
  per_page?: number;
  page?: number;
}

/**
 * Resposta da API para listagem de eventos da agenda.
 */
export interface EventListResponse {
  success: boolean;
  data: Event[];
  meta?: PaginationMeta;
}
