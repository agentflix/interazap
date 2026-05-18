import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import type { ReasonLoss, ReasonLossFilters, ReasonLossItemResponse, ReasonLossListResponse } from '@core/models/reason-loss.model';
export type { ReasonLoss, ReasonLossFilters, ReasonLossItemResponse, ReasonLossListResponse } from '@core/models/reason-loss.model';



/**
 * Service for managing CRM reason losses (lost deal reasons).
 *
 * @remarks
 * Provides CRUD operations for reason loss entries used to classify
 * why negotiations or deals were lost, including reordering support.
 *
 * @example
 * ```typescript
 * const service = inject(ReasonLossService);
 * service.list({ active: true }).subscribe();
 * ```
 */
@Injectable({ providedIn: 'root' })
export class ReasonLossService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/reason-losses`;

  /**
   * Lists reason losses with optional filters and pagination.
   *
   * @param filters - Optional filters (active, search, sort, pagination)
   * @returns Observable with paginated list response
   */
  list(filters: ReasonLossFilters = {}): Observable<ReasonLossListResponse> {
    let params = new HttpParams();
    params = this.appendBoolean(params, 'active', filters.active);
    params = this.appendTrimmedString(params, 'search', filters.search);
    params = this.appendTrimmedString(params, 'sort_by', filters.sort_by);
    params = this.appendTrimmedString(params, 'sort_dir', filters.sort_dir);
    params = this.appendNumber(params, 'per_page', filters.per_page);
    params = this.appendNumber(params, 'page', filters.page);
    return this.http.get<ReasonLossListResponse>(this.baseUrl, { params });
  }

  /**
   * Retrieves all reason losses without pagination.
   *
   * @returns Observable with array of all reason losses
   */
  all(): Observable<{ data: ReasonLoss[] }> {
    return this.http.get<{ data: ReasonLoss[] }>(`${this.baseUrl}/all`);
  }

  /**
   * Retrieves a single reason loss by ID.
   *
   * @param id - Reason loss identifier
   * @returns Observable with single reason loss response
   */
  show(id: string | number): Observable<ReasonLossItemResponse> {
    return this.http.get<ReasonLossItemResponse>(`${this.baseUrl}/${id}`);
  }

  /**
   * Creates a new reason loss entry.
   *
   * @param data - Partial reason loss data
   * @returns Observable with created reason loss response
   */
  create(data: Partial<ReasonLoss>): Observable<ReasonLossItemResponse> {
    return this.http.post<ReasonLossItemResponse>(this.baseUrl, data);
  }

  /**
   * Updates an existing reason loss entry.
   *
   * @param id - Reason loss identifier
   * @param data - Partial reason loss data to update
   * @returns Observable with updated reason loss response
   */
  update(id: string | number, data: Partial<ReasonLoss>): Observable<ReasonLossItemResponse> {
    return this.http.put<ReasonLossItemResponse>(`${this.baseUrl}/${id}`, data);
  }

  /**
   * Deletes a reason loss entry.
   *
   * @param id - Reason loss identifier
   * @returns Observable completing on deletion
   */
  delete(id: string | number): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${id}`);
  }

  /**
   * Reorders reason losses by providing an ordered array of IDs.
   *
   * @param order - Array of reason loss IDs in desired order
   * @returns Observable completing on reorder
   */
  reorder(order: (string | number)[]): Observable<null> {
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
