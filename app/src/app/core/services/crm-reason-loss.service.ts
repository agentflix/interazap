import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import type {
  ReasonLoss,
  ReasonLossFilters,
  ReasonLossListResponse,
  ReasonLossResponse,
} from '@core/models/reason-loss.model';

/**
 * Service for managing CRM reason losses.
 * Preserved verbatim from source — no business logic changes.
 */
@Injectable({ providedIn: 'root' })
export class ReasonLossService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/reason-losses`;

  /** List reason losses with filters and pagination. */
  list(filters: ReasonLossFilters = {}): Observable<ReasonLossListResponse> {
    let params = new HttpParams();
    params = this.appendTrimmedString(params, 'search', filters.search);
    params = this.appendBoolean(params, 'is_active', filters.is_active);
    params = this.appendNumber(params, 'per_page', filters.per_page);
    params = this.appendNumber(params, 'page', filters.page);
    params = this.appendTrimmedString(params, 'sort_by', filters.sort_by);
    params = this.appendTrimmedString(params, 'sort_dir', filters.sort_dir);
    return this.http.get<ReasonLossListResponse>(this.baseUrl, { params });
  }

  /** Show a single reason loss. */
  show(id: string): Observable<ReasonLossResponse> {
    return this.http.get<ReasonLossResponse>(`${this.baseUrl}/${id}`);
  }

  /** Create a new reason loss. */
  create(data: Partial<ReasonLoss>): Observable<ReasonLossResponse> {
    return this.http.post<ReasonLossResponse>(this.baseUrl, data);
  }

  /** Update an existing reason loss. */
  update(id: string, data: Partial<ReasonLoss>): Observable<ReasonLossResponse> {
    return this.http.put<ReasonLossResponse>(`${this.baseUrl}/${id}`, data);
  }

  /** Delete a reason loss. */
  delete(id: string): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${id}`);
  }

  /** Reorder reason losses. */
  reorder(order: string[]): Observable<null> {
    return this.http.post<null>(`${this.baseUrl}/reorder`, { order });
  }

  private appendTrimmedString(params: HttpParams, key: string, value?: string): HttpParams {
    if (value === undefined) return params;
    const trimmed = value.trim();
    if (trimmed.length === 0) return params;
    return params.set(key, trimmed);
  }

  private appendBoolean(params: HttpParams, key: string, value?: boolean): HttpParams {
    if (value === undefined) return params;
    return params.set(key, String(value));
  }

  private appendNumber(params: HttpParams, key: string, value?: number): HttpParams {
    if (value === undefined) return params;
    return params.set(key, String(value));
  }
}
