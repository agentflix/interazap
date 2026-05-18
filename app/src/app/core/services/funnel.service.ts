import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '@env/environment';
import { type PaginatedResponse } from '@core/models/pagination.model';
import type { Funnel, FunnelFilters, FunnelPayload, FunnelStep, FunnelStepPayload } from '@core/models/funnel.model';
export type { Funnel, FunnelFilters, FunnelPayload, FunnelStep, FunnelStepPayload } from '@core/models/funnel.model';



/**
 * Service for managing CRM funnels and their steps.
 *
 * @remarks
 * Provides CRUD operations for sales funnels and pipeline steps,
 * including bulk step reordering support.
 *
 * @example
 * ```typescript
 * const service = inject(FunnelService);
 * service.list({ is_active: true }).subscribe();
 * ```
 */
@Injectable({ providedIn: 'root' })
export class FunnelService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/funnels`;

  // ─────────────────────────────────────────────────────────────────────────────
  // Funnels CRUD
  // ─────────────────────────────────────────────────────────────────────────────

  /**
   * Lists funnels with optional filters and pagination.
   *
   * @param filters - Optional filters (search, is_active, pagination)
   * @returns Observable with paginated funnel list
   */
  list(filters: FunnelFilters = {}): Observable<PaginatedResponse<Funnel>> {
    let params = new HttpParams();

    if (filters.search !== undefined && filters.search.trim() !== '') {
      params = params.set('search', filters.search);
    }
    if (filters.is_active !== undefined && filters.is_active !== 'all') {
      params = params.set(
        'is_active',
        String(filters.is_active === true || filters.is_active === 'active'),
      );
    }
    if (filters.per_page !== undefined) params = params.set('per_page', String(filters.per_page));
    if (filters.page !== undefined) params = params.set('page', String(filters.page));

    return this.http.get<PaginatedResponse<Funnel>>(this.baseUrl, { params });
  }

  /**
   * Retrieves all funnels without pagination (for dropdowns/selects).
   *
   * @returns Observable with all funnels wrapped in data object
   */
  all(): Observable<{ data: { funnels: Funnel[] } }> {
    return this.http.get<{ data: Funnel[] | { funnels?: Funnel[] } }>(`${this.baseUrl}/all`).pipe(
      map((response) => {
        const data = response.data;
        const funnels = Array.isArray(data) ? data : (data?.funnels ?? []);

        return { data: { funnels } };
      }),
    );
  }

  /**
   * Retrieves a single funnel by ID.
   *
   * @param id - Funnel identifier
   * @returns Observable with funnel data
   */
  get(id: string | number): Observable<{ data: { funnel: Funnel } }> {
    return this.http.get<{ data: { funnel: Funnel } }>(`${this.baseUrl}/${id}`);
  }

  /**
   * Creates a new funnel.
   *
   * @param payload - Funnel data (name, description, is_active, steps)
   * @returns Observable with created funnel
   */
  create(payload: FunnelPayload): Observable<{ data: { funnel: Funnel } }> {
    return this.http.post<{ data: { funnel: Funnel } }>(this.baseUrl, payload);
  }

  /**
   * Updates an existing funnel.
   *
   * @param id - Funnel identifier
   * @param payload - Partial funnel data to update
   * @returns Observable with updated funnel
   */
  update(id: string | number, payload: FunnelPayload): Observable<{ data: { funnel: Funnel } }> {
    return this.http.put<{ data: { funnel: Funnel } }>(`${this.baseUrl}/${id}`, payload);
  }

  /**
   * Deletes a funnel.
   *
   * @param id - Funnel identifier
   * @returns Observable completing on deletion
   */
  delete(id: string | number): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${id}`);
  }

  // ─────────────────────────────────────────────────────────────────────────────
  // Funnel Steps CRUD
  // ─────────────────────────────────────────────────────────────────────────────

  /**
   * Lists all steps for a specific funnel.
   *
   * @param funnelId - Parent funnel identifier
   * @returns Observable with array of steps
   */
  listSteps(funnelId: string | number): Observable<{ data: { steps: FunnelStep[] } }> {
    return this.http.get<{ data: { steps: FunnelStep[] } }>(`${this.baseUrl}/${funnelId}/steps`);
  }

  /**
   * Creates a new step within a funnel.
   *
   * @param funnelId - Parent funnel identifier
   * @param payload - Step data (name, order, color, is_active)
   * @returns Observable with created step
   */
  createStep(
    funnelId: string | number,
    payload: FunnelStepPayload,
  ): Observable<{ data: { step: FunnelStep } }> {
    return this.http.post<{ data: { step: FunnelStep } }>(
      `${this.baseUrl}/${funnelId}/steps`,
      payload,
    );
  }

  /**
   * Updates an existing funnel step.
   *
   * @param funnelId - Parent funnel identifier
   * @param stepId - Step identifier
   * @param payload - Partial step data to update
   * @returns Observable with updated step
   */
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

  /**
   * Deletes a step from a funnel.
   *
   * @param funnelId - Parent funnel identifier
   * @param stepId - Step identifier
   * @returns Observable completing on deletion
   */
  deleteStep(funnelId: string | number, stepId: string | number): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${funnelId}/steps/${stepId}`);
  }

  /**
   * Reorders steps within a funnel by providing an ordered list of step IDs.
   *
   * @param funnelId - Parent funnel identifier
   * @param stepIds - Array of step IDs in the desired order
   * @returns Observable completing on reorder
   */
  reorderSteps(funnelId: string | number, stepIds: (string | number)[]): Observable<null> {
    const steps = stepIds.map((id, index) => ({ id, order: index + 1 }));
    return this.http.post<null>(`${this.baseUrl}/${funnelId}/steps/reorder`, { steps });
  }
}
