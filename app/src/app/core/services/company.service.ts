import { type HttpResponse, HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import { type TenantDetails } from '@shared/models/tenant-details.model';
import { type PaginatedResponse } from '@core/models/pagination.model';
import type { Company, CompanyFilters } from '@core/models/company.model';

@Injectable({ providedIn: 'root' })
export class CompanyService {
  private readonly baseUrl = `${environment.apiUrl}/platform/tenants`;
  private readonly http = inject(HttpClient);

  list(filters: CompanyFilters = {}): Observable<PaginatedResponse<Company>> {
    const params = this.buildListParams(filters);

    return this.http.get<PaginatedResponse<Company>>(this.baseUrl, { params });
  }

  create(data: Partial<Company>): Observable<{ data: Company }> {
    return this.http.post<{ data: Company }>(this.baseUrl, data);
  }

  find(id: string | number): Observable<{ data: Company }> {
    return this.http.get<{ data: Company }>(`${this.baseUrl}/${id}`);
  }

  update(id: string | number, data: Partial<Company>): Observable<{ data: Company }> {
    return this.http.put<{ data: Company }>(`${this.baseUrl}/${id}`, data);
  }

  delete(id: string | number): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${id}`);
  }

  toggleActive(id: string | number): Observable<{ data: Company }> {
    return this.http.patch<{ data: Company }>(`${this.baseUrl}/${id}/toggle-active`, {});
  }

  restore(id: string | number): Observable<{ data: Company }> {
    return this.http.post<{ data: Company }>(`${this.baseUrl}/${id}/restore`, {});
  }

  forceDelete(id: string | number): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${id}/force`);
  }

  purge(id: string | number, password: string): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${id}/purge`, {
      body: { password },
    });
  }

  /**
   * Fetch detailed tenant info: company data, contracted plan & resource usage.
   */
  details(id: string | number): Observable<{ data: TenantDetails }> {
    return this.http.get<{ data: TenantDetails }>(`${this.baseUrl}/${id}/details`);
  }

  export(filters: CompanyFilters = {}): Observable<HttpResponse<Blob>> {
    const params = this.buildExportParams(filters);

    return this.http.get(`${this.baseUrl}/export`, {
      params,
      responseType: 'blob',
      observe: 'response',
    });
  }

  private buildListParams(filters: CompanyFilters): HttpParams {
    let params = new HttpParams();
    params = this.appendTrimmedString(params, 'search', filters.search);
    params = this.appendBoolean(params, 'is_active', filters.is_active);
    params = this.appendFlag(params, 'trashed', filters.trashed);
    params = this.appendScalar(params, 'per_page', filters.per_page);
    params = this.appendScalar(params, 'page', filters.page);
    params = this.appendScalar(params, 'sort_by', filters.sort_by);
    params = this.appendScalar(params, 'sort_dir', filters.sort_dir);
    params = this.appendScalar(params, 'plan_id', filters.plan_id);
    params = this.appendTrimmedString(params, 'created_from', filters.created_from);
    params = this.appendTrimmedString(params, 'created_to', filters.created_to);
    return params;
  }

  private buildExportParams(filters: CompanyFilters): HttpParams {
    let params = new HttpParams();
    params = this.appendTrimmedString(params, 'search', filters.search);
    params = this.appendBoolean(params, 'is_active', filters.is_active);
    params = this.appendFlag(params, 'trashed', filters.trashed);
    params = this.appendScalar(params, 'sort_by', filters.sort_by);
    params = this.appendScalar(params, 'sort_dir', filters.sort_dir);
    params = this.appendTrimmedString(params, 'created_from', filters.created_from);
    params = this.appendTrimmedString(params, 'created_to', filters.created_to);
    return params;
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

  private appendFlag(params: HttpParams, key: string, value?: boolean): HttpParams {
    if (value !== true) return params;
    return params.set(key, 'true');
  }

  private appendScalar(
    params: HttpParams,
    key: string,
    value?: string | number | null,
  ): HttpParams {
    if (value === undefined || value === null) return params;
    return params.set(key, String(value));
  }
}
