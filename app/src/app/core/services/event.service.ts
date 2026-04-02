import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { map, type Observable } from 'rxjs';
import { environment } from '@env/environment';
import type { Paginated, PaginationMeta } from '@shared/types/pagination';

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

/**
 * Servico responsavel pelo gerenciamento de eventos do CRM.
 * Suporta criacao, edicao, listagem com filtros e exclusao de eventos
 * como reuniones, chamadas, tarefas e prazos.
 *
 * @class EventService
 * @example
 * ```ts
 * const eventSvc = inject(EventService);
 * eventSvc.list({ type: 'meeting', start_date: '2026-01-01' }).subscribe();
 * ```
 */
@Injectable({ providedIn: 'root' })
export class EventService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/events`;

  /**
   * Lista eventos com suporte a filtros avanzados e paginacao.
   *
   * @param filters - Filtros: search, start_date, end_date, user_id, participant_id,
   *   linkable_type, linkable_id, is_all_day, recurrence, location, has_reminders,
   *   status, type, sort_by, sort_dir, per_page, page
   * @returns Observable com lista paginada de eventos
   */
  list(filters: EventFilters = {}): Observable<Paginated<Event>> {
    let params = new HttpParams();
    if (filters.search) params = params.set('search', filters.search);
    if (filters.start_date) params = params.set('start_date', filters.start_date);
    if (filters.end_date) params = params.set('end_date', filters.end_date);
    if (filters.user_id) params = params.set('user_id', filters.user_id);
    if (filters.participant_id) params = params.set('participant_id', filters.participant_id);
    if (filters.linkable_type) params = params.set('linkable_type', filters.linkable_type);
    if (filters.linkable_id) params = params.set('linkable_id', filters.linkable_id);
    if (filters.is_all_day !== undefined)
      params = params.set('is_all_day', String(filters.is_all_day));
    if (filters.recurrence) params = params.set('recurrence', filters.recurrence);
    if (filters.location) params = params.set('location', filters.location);
    if (filters.has_reminders !== undefined)
      params = params.set('has_reminders', String(filters.has_reminders));
    if (filters.status) params = params.set('status', filters.status);
    if (filters.type) params = params.set('type', filters.type);
    if (filters.sort_by) params = params.set('sort_by', filters.sort_by);
    if (filters.sort_dir) params = params.set('sort_dir', filters.sort_dir);
    if (filters.per_page) params = params.set('per_page', String(filters.per_page));
    if (filters.page) params = params.set('page', String(filters.page));

    return this.http
      .get<EventListResponse>(this.baseUrl, { params })
      .pipe(map((resp) => this.normalizeList(resp)));
  }

  /**
   * Cria um novo evento.
   *
   * @param data - Dados do evento a ser criado
   * @returns Observable com o evento criado
   */
  create(data: Partial<Event>): Observable<Event> {
    return this.http.post<{ data: Event }>(this.baseUrl, data).pipe(map((resp) => resp.data));
  }

  /**
   * Atualiza um evento existente.
   *
   * @param id - Identificador do evento
   * @param data - Dados a serem atualizados
   * @returns Observable com o evento atualizado
   */
  update(id: string, data: Partial<Event>): Observable<Event> {
    return this.http
      .put<{ data: Event }>(`${this.baseUrl}/${id}`, data)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Remove um evento.
   *
   * @param id - Identificador do evento
   * @returns Observable vazio indicando sucesso
   */
  delete(id: string): Observable<void> {
    return this.http.delete<void>(`${this.baseUrl}/${id}`);
  }

  private normalizeList(resp: EventListResponse): Paginated<Event> {
    const meta = resp.meta ?? {
      current_page: 1,
      last_page: 1,
      per_page: resp.data.length,
      total: resp.data.length,
    };

    return {
      data: resp.data ?? [],
      meta,
    };
  }
}
