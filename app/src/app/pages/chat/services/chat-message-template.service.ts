import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { type Observable, map } from 'rxjs';
import { environment } from '@env/environment';
import {
  type ChatMessageTemplate,
  type ChatMessageTemplateFilters,
  type ChatMessageTemplatePayload,
  type ChatMessageTemplateSyncResponse,
} from '@core/models/chat-message-template.model';
import { type PaginatedResponse } from '@core/models/pagination.model';

interface SingleEnvelope<T> {
  data: T;
}

/**
 * CRUD client for `chat/message-templates`.
 *
 * Backend endpoints (TASK-050.A5/A6):
 * - `GET    /api/chat/message-templates`            — list with filters
 * - `GET    /api/chat/message-templates/{id}`       — fetch one
 * - `POST   /api/chat/message-templates`            — create
 * - `PUT    /api/chat/message-templates/{id}`       — update
 * - `DELETE /api/chat/message-templates/{id}`       — delete
 * - `POST   /api/chat/message-templates/sync`       — sync from Meta
 */
@Injectable({ providedIn: 'root' })
export class ChatMessageTemplateService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/chat/message-templates`;

  /** Lista paginada de templates com filtros opcionais. */
  list(
    filters: ChatMessageTemplateFilters = {},
  ): Observable<PaginatedResponse<ChatMessageTemplate>> {
    let params = new HttpParams();

    if (filters.search) {
      params = params.set('search', filters.search);
    }
    if (filters.status) {
      params = params.set('status', filters.status);
    }
    if (filters.chat_instance_id) {
      params = params.set('chat_instance_id', filters.chat_instance_id);
    }
    if (filters.provider) {
      params = params.set('provider', filters.provider);
    }
    if (filters.page) {
      params = params.set('page', String(filters.page));
    }
    if (filters.per_page) {
      params = params.set('per_page', String(filters.per_page));
    }

    return this.http.get<PaginatedResponse<ChatMessageTemplate>>(this.baseUrl, { params });
  }

  /** Busca um template pelo id. */
  get(id: string): Observable<ChatMessageTemplate> {
    return this.http
      .get<SingleEnvelope<ChatMessageTemplate>>(`${this.baseUrl}/${id}`)
      .pipe(map((response) => response.data));
  }

  /** Cria um template (Meta entra em status `pending`). */
  create(payload: ChatMessageTemplatePayload): Observable<ChatMessageTemplate> {
    return this.http
      .post<SingleEnvelope<ChatMessageTemplate>>(this.baseUrl, payload)
      .pipe(map((response) => response.data));
  }

  /**
   * Atualiza um template.
   * Para templates Meta, somente `is_active` e `shortcut` podem ser alterados (ver TASK-050.A4).
   */
  update(id: string, payload: ChatMessageTemplatePayload): Observable<ChatMessageTemplate> {
    return this.http
      .put<SingleEnvelope<ChatMessageTemplate>>(`${this.baseUrl}/${id}`, payload)
      .pipe(map((response) => response.data));
  }

  /** Remove um template. Para Meta, remove também na Meta (best effort). */
  delete(id: string): Observable<void> {
    return this.http.delete<void>(`${this.baseUrl}/${id}`);
  }

  /** Sincroniza com a Meta via Gateway, retornando o total atualizado. */
  sync(chatInstanceId: string): Observable<ChatMessageTemplateSyncResponse> {
    return this.http
      .post<SingleEnvelope<ChatMessageTemplateSyncResponse>>(`${this.baseUrl}/sync`, {
        chat_instance_id: chatInstanceId,
      })
      .pipe(map((response) => response.data));
  }
}
