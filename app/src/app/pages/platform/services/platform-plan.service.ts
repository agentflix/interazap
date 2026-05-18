import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable, map } from 'rxjs';
import { environment } from '@env/environment';
import { type PlatformPlan, type PlatformPlanFilters, type PlatformPlanPayload } from '../models';
import type { Paginated } from '@platform/models/platform-plan.model';
export type { Paginated } from '@platform/models/platform-plan.model';


// Re-export models for backwards compatibility
export type { PlatformPlan, PlatformPlanFilters, PlatformPlanPayload } from '../models';


interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

/**
 * Platform plan service page component for the Platform module.
 */
@Injectable({ providedIn: 'root' })
export class PlatformPlanService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/platform/plans`;

  list(filters: PlatformPlanFilters = {}): Observable<Paginated<PlatformPlan>> {
    let params = new HttpParams();
    if (filters.search) params = params.set('search', filters.search);
    if (filters.page) params = params.set('page', String(filters.page));
    if (filters.per_page) params = params.set('per_page', String(filters.per_page));

    return this.http.get<Paginated<PlatformPlan>>(this.baseUrl, { params }).pipe(
      map((resp) => ({
        data: resp.data ?? [],
        meta: resp.meta ?? {
          current_page: 1,
          total: (resp.data ?? []).length,
          per_page: (resp.data ?? []).length,
          last_page: 1,
        },
      })),
    );
  }

  getById(id: string): Observable<ApiResponse<PlatformPlan>> {
    return this.http.get<ApiResponse<PlatformPlan>>(`${this.baseUrl}/${id}`);
  }

  create(payload: PlatformPlanPayload): Observable<ApiResponse<PlatformPlan>> {
    return this.http.post<ApiResponse<PlatformPlan>>(this.baseUrl, payload);
  }

  update(id: string, payload: PlatformPlanPayload): Observable<ApiResponse<PlatformPlan>> {
    return this.http.put<ApiResponse<PlatformPlan>>(`${this.baseUrl}/${id}`, payload);
  }

  delete(id: string): Observable<void> {
    return this.http.delete<void>(`${this.baseUrl}/${id}`);
  }

  toggle(id: string): Observable<ApiResponse<PlatformPlan>> {
    return this.http.patch<ApiResponse<PlatformPlan>>(`${this.baseUrl}/${id}/toggle`, {});
  }

  validateSlug(slug: string, planId?: string): Observable<ApiResponse<{ available: boolean }>> {
    let params = new HttpParams().set('slug', slug);
    if (planId) params = params.set('plan_id', planId);

    return this.http.get<ApiResponse<{ available: boolean }>>(`${this.baseUrl}/validate-slug`, {
      params,
    });
  }
}
