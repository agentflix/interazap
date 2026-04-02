import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { type Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '@env/environment';
import { type Paginated } from '@shared/types/pagination';
import {
  type MasterPrompt,
  type MasterPromptPayload,
  type SegmentPrompt,
  type SegmentPromptPayload,
  type PlanPrompt,
  type PlanPromptPayload,
  type QuarantinedPrompt,
} from '../../pages/ai/models/ai.model';

/** Single-item API response wrapper. */
interface ApiDataResponse<T> {
  data: T;
}

/** Shape returned by the prompt resolve preview endpoint. */
interface PromptResolveResponse {
  resolved_prompt: string;
  components: {
    master: string;
    segment: string;
    plan: string;
    tenant: string;
  };
}

/**
 * Service for super admin AI prompt governance endpoints.
 */
@Injectable({ providedIn: 'root' })
export class AiGovernanceService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/platform/ai/prompts`;

  listMasters(filters?: { search?: string; page?: number }): Observable<Paginated<MasterPrompt>> {
    const params = new URLSearchParams();
    if (filters?.search) params.set('search', filters.search);
    if (filters?.page) params.set('page', String(filters.page));

    const query = params.toString();
    return this.http.get<Paginated<MasterPrompt>>(
      `${this.baseUrl}/masters${query ? `?${query}` : ''}`,
    );
  }

  getMaster(id: string): Observable<MasterPrompt> {
    return this.http
      .get<ApiDataResponse<MasterPrompt>>(`${this.baseUrl}/masters/${id}`)
      .pipe(map((response) => response.data));
  }

  createMaster(payload: MasterPromptPayload): Observable<MasterPrompt> {
    return this.http
      .post<ApiDataResponse<MasterPrompt>>(`${this.baseUrl}/masters`, payload)
      .pipe(map((response) => response.data));
  }

  updateMaster(id: string, payload: MasterPromptPayload): Observable<MasterPrompt> {
    return this.http
      .put<ApiDataResponse<MasterPrompt>>(`${this.baseUrl}/masters/${id}`, payload)
      .pipe(map((response) => response.data));
  }

  deleteMaster(id: string): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/masters/${id}`);
  }

  toggleMaster(id: string): Observable<MasterPrompt> {
    return this.http
      .patch<ApiDataResponse<MasterPrompt>>(`${this.baseUrl}/masters/${id}/toggle`, {})
      .pipe(map((response) => response.data));
  }

  listSegments(filters?: {
    search?: string;
    page?: number;
    withTrashed?: boolean;
  }): Observable<Paginated<SegmentPrompt>> {
    const params = new URLSearchParams();
    if (filters?.search) params.set('search', filters.search);
    if (filters?.page) params.set('page', String(filters.page));
    if (filters?.withTrashed) params.set('with_trashed', '1');

    const query = params.toString();
    return this.http.get<Paginated<SegmentPrompt>>(
      `${this.baseUrl}/segments${query ? `?${query}` : ''}`,
    );
  }

  getSegment(id: string): Observable<SegmentPrompt> {
    return this.http
      .get<ApiDataResponse<SegmentPrompt>>(`${this.baseUrl}/segments/${id}`)
      .pipe(map((response) => response.data));
  }

  createSegment(payload: SegmentPromptPayload): Observable<SegmentPrompt> {
    return this.http
      .post<ApiDataResponse<SegmentPrompt>>(`${this.baseUrl}/segments`, payload)
      .pipe(map((response) => response.data));
  }

  updateSegment(id: string, payload: SegmentPromptPayload): Observable<SegmentPrompt> {
    return this.http
      .put<ApiDataResponse<SegmentPrompt>>(`${this.baseUrl}/segments/${id}`, payload)
      .pipe(map((response) => response.data));
  }

  deleteSegment(id: string): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/segments/${id}`);
  }

  restoreSegment(id: string): Observable<SegmentPrompt> {
    return this.http
      .post<{ data: SegmentPrompt }>(`${this.baseUrl}/segments/${id}/restore`, {})
      .pipe(map((response) => response.data));
  }

  listPlans(filters?: { search?: string; page?: number }): Observable<Paginated<PlanPrompt>> {
    const params = new URLSearchParams();
    if (filters?.search) params.set('search', filters.search);
    if (filters?.page) params.set('page', String(filters.page));

    const query = params.toString();
    return this.http.get<Paginated<PlanPrompt>>(`${this.baseUrl}/plans${query ? `?${query}` : ''}`);
  }

  getPlan(id: string): Observable<PlanPrompt> {
    return this.http
      .get<ApiDataResponse<PlanPrompt>>(`${this.baseUrl}/plans/${id}`)
      .pipe(map((response) => response.data));
  }

  updatePlan(id: string, payload: PlanPromptPayload): Observable<PlanPrompt> {
    return this.http
      .put<ApiDataResponse<PlanPrompt>>(`${this.baseUrl}/plans/${id}`, payload)
      .pipe(map((response) => response.data));
  }

  listQuarantine(filters?: {
    search?: string;
    page?: number;
  }): Observable<Paginated<QuarantinedPrompt>> {
    const params = new URLSearchParams();
    if (filters?.search) params.set('search', filters.search);
    if (filters?.page) params.set('page', String(filters.page));

    const query = params.toString();
    return this.http.get<Paginated<QuarantinedPrompt>>(
      `${this.baseUrl}/quarantine${query ? `?${query}` : ''}`,
    );
  }

  approvePrompt(id: string): Observable<null> {
    return this.http.post<null>(`${this.baseUrl}/quarantine/${id}/approve`, {});
  }

  rejectPrompt(id: string, reason: string): Observable<null> {
    return this.http.post<null>(`${this.baseUrl}/quarantine/${id}/reject`, { reason });
  }

  assignSegment(tenantId: string, segmentId: string): Observable<null> {
    return this.http.put<null>(`${environment.apiUrl}/platform/tenants/${tenantId}/segment`, {
      segment_id: segmentId,
    });
  }

  resolvePromptPreview(): Observable<PromptResolveResponse> {
    return this.http
      .get<ApiDataResponse<PromptResolveResponse>>(`${environment.apiUrl}/ai/prompt/resolve`)
      .pipe(map((response) => response.data));
  }
}
