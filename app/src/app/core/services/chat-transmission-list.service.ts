import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { type Observable, map } from 'rxjs';
import { environment } from '@env/environment';
import type { ChatTransmissionList, ChatTransmissionListListResponse, ChatTransmissionListPayload, ChatTransmissionListPreview, ChatTransmissionListResponse } from '@core/models/chat-transmission-list.model';
export type { ChatTransmissionList, ChatTransmissionListListResponse, ChatTransmissionListPayload, ChatTransmissionListPreview, ChatTransmissionListResponse } from '@core/models/chat-transmission-list.model';


/** Primitive metadata shape returned by transmission list API. */
interface TransmissionListMeta {
  current_page?: number;
  last_page?: number;
  per_page?: number;
  total?: number;
}

/** Chat transmission list entity used by transmission list screens. */

/** Payload used to create/update a transmission list. */

/** API shape returned by list endpoint after normalization. */

interface ChatTransmissionListListEnvelope {
  data?: ChatTransmissionList[];
  meta?: TransmissionListMeta;
}

interface ChatTransmissionListListNestedEnvelope {
  data?: {
    data?: ChatTransmissionList[];
    meta?: TransmissionListMeta;
  };
  meta?: TransmissionListMeta;
}

/** API shape returned by single item endpoints. */

/** Preview API response payload. */

/** Service responsible for Chat Transmission List CRUD and helper endpoints. */
@Injectable({ providedIn: 'root' })
export class ChatTransmissionListService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = `${environment.apiUrl}/chat/transmission-lists`;

  /** Lists transmission lists with pagination and optional search. */
  list(
    params: { per_page?: number; page?: number; search?: string } = {},
  ): Observable<ChatTransmissionListListResponse> {
    let httpParams = new HttpParams();

    if (params.per_page) {
      httpParams = httpParams.set('per_page', String(params.per_page));
    }
    if (params.page) {
      httpParams = httpParams.set('page', String(params.page));
    }
    if (params.search) {
      httpParams = httpParams.set('search', params.search);
    }

    return this.http
      .get<
        ChatTransmissionListListEnvelope | ChatTransmissionListListNestedEnvelope
      >(this.apiUrl, { params: httpParams })
      .pipe(
        map((response) => {
          const data = this.extractData(response);
          const meta = this.extractMeta(response);

          return {
            success: true,
            data: {
              data,
              current_page: meta?.current_page,
              last_page: meta?.last_page,
              per_page: meta?.per_page,
              total: meta?.total,
            },
          };
        }),
      );
  }

  /** Loads a transmission list by id. */
  show(id: string): Observable<ChatTransmissionListResponse> {
    return this.http
      .get<{ data: ChatTransmissionList }>(`${this.apiUrl}/${id}`)
      .pipe(map((response) => ({ success: true, data: response.data })));
  }

  /** Creates a new transmission list. */
  create(payload: ChatTransmissionListPayload): Observable<ChatTransmissionListResponse> {
    return this.http
      .post<{ data: ChatTransmissionList }>(this.apiUrl, payload)
      .pipe(map((response) => ({ success: true, data: response.data })));
  }

  /** Updates an existing transmission list. */
  update(id: string, payload: Partial<ChatTransmissionListPayload>): Observable<ChatTransmissionListResponse> {
    return this.http
      .put<{ data: ChatTransmissionList }>(`${this.apiUrl}/${id}`, payload)
      .pipe(map((response) => ({ success: true, data: response.data })));
  }

  /** Deletes a transmission list. */
  delete(id: string): Observable<null> {
    return this.http.delete<null>(`${this.apiUrl}/${id}`);
  }

  /** Triggers transmission list sending. */
  send(id: string): Observable<null> {
    return this.http.post<null>(`${this.apiUrl}/${id}/send`, {});
  }

  /** Generates message preview based on contact sample interpolation. */
  preview(message: string): Observable<ChatTransmissionListPreview> {
    return this.http
      .post<{ data: ChatTransmissionListPreview }>(`${this.apiUrl}/preview`, { message })
      .pipe(map((response) => response.data));
  }

  /** Calculates target audience estimate for current filter criteria. */
  audience(criteria: {
    tags?: string[];
    status?: string;
    company_id?: string | null;
  }): Observable<{ count: number }> {
    return this.http
      .post<{ success: boolean; data: { count: number } }>(`${this.apiUrl}/audience`, { criteria })
      .pipe(map((response) => response.data));
  }

  private extractData(
    response: ChatTransmissionListListEnvelope | ChatTransmissionListListNestedEnvelope,
  ): ChatTransmissionList[] {
    if (Array.isArray(response.data)) {
      return response.data;
    }

    if (response.data && Array.isArray(response.data.data)) {
      return response.data.data;
    }

    return [];
  }

  private extractMeta(
    response: ChatTransmissionListListEnvelope | ChatTransmissionListListNestedEnvelope,
  ): TransmissionListMeta | undefined {
    if (response.data && !Array.isArray(response.data)) {
      return response.data.meta ?? response.meta;
    }

    return response.meta;
  }
}
