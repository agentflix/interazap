import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { type Observable, map } from 'rxjs';
import { environment } from '@env/environment';
import type {
  Funnel,
  FunnelFilters,
  FunnelListResponse,
  FunnelResponse,
  FunnelPayload,
  FunnelStepPayload,
  FunnelStep,
} from '@core/models/funnel.model';

/**
 * Service for managing CRM funnels and funnel steps.
 * Preserved verbatim from source — no business logic changes.
 */
@Injectable({ providedIn: 'root' })
export class FunnelService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/funnels`;

  // ─── Funnels CRUD ──────────────────────────────────────────────────────────

  /** List funnels with filters and pagination. */
  list(filters: FunnelFilters = {}): Observable<FunnelListResponse> {
    let params = new HttpParams();
    params = this.appendTrimmedString(params, 'search', filters.search);
    params = this.appendBoolean(
      params,
      'is_active',
      typeof filters.is_active === 'boolean' ? filters.is_active : undefined,
    );
    params = this.appendNumber(params, 'per_page', filters.per_page);
    params = this.appendNumber(params, 'page', filters.page);
    params = this.appendTrimmedString(params, 'sort_by', filters.sort_by);
    params = this.appendTrimmedString(params, 'sort_dir', filters.sort_dir);
    return this.http.get<FunnelListResponse>(this.baseUrl, { params });
  }

  /** List all funnels without pagination. */
  all(): Observable<{ data: { funnels: Funnel[] } }> {
    return this.http.get<{ data: Funnel[] | { funnels?: Funnel[] } }>(`${this.baseUrl}/all`).pipe(
      map((response) => {
        const data = response.data;
        const funnels = Array.isArray(data) ? data : (data?.funnels ?? []);
        return { data: { funnels } };
      }),
    );
  }

  /** Get a single funnel by ID. */
  get(id: string | number): Observable<FunnelResponse> {
    return this.http.get<FunnelResponse>(`${this.baseUrl}/${id}`);
  }

  /** Create a new funnel. */
  create(payload: FunnelPayload): Observable<FunnelResponse> {
    return this.http.post<FunnelResponse>(this.baseUrl, payload);
  }

  /** Update an existing funnel. */
  update(id: string | number, payload: FunnelPayload): Observable<FunnelResponse> {
    return this.http.put<FunnelResponse>(`${this.baseUrl}/${id}`, payload);
  }

  /** Delete a funnel. */
  delete(id: string | number): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${id}`);
  }

  // ─── Funnel Steps CRUD ─────────────────────────────────────────────────────

  /** List steps for a funnel. */
  listSteps(funnelId: string | number): Observable<{ data: { steps: FunnelStep[] } }> {
    return this.http.get<{ data: { steps: FunnelStep[] } }>(`${this.baseUrl}/${funnelId}/steps`);
  }

  /** Create a new step in a funnel. */
  createStep(
    funnelId: string | number,
    payload: FunnelStepPayload,
  ): Observable<{ data: { step: FunnelStep } }> {
    return this.http.post<{ data: { step: FunnelStep } }>(
      `${this.baseUrl}/${funnelId}/steps`,
      payload,
    );
  }

  /** Update an existing step. */
  updateStep(
    funnelId: string | number,
    stepId: string | number,
    payload: FunnelStepPayload,
  ): Observable<{ data: { step: FunnelStep } }> {
    return this.http.put<{ data: { step: FunnelStep } }>(
      `${this.baseUrl}/${funnelId}/steps/${stepId}`,
      payload,
    );
  }

  /** Delete a step from a funnel. */
  deleteStep(funnelId: string | number, stepId: string | number): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${funnelId}/steps/${stepId}`);
  }

  /** Reorder steps in a funnel. */
  reorderSteps(funnelId: string | number, stepIds: (string | number)[]): Observable<null> {
    const steps = stepIds.map((id, index) => ({ id, order: index + 1 }));
    return this.http.post<null>(`${this.baseUrl}/${funnelId}/steps/reorder`, { steps });
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
