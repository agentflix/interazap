import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { type Observable, map } from 'rxjs';
import { environment } from '@env/environment';
import type { CRMCompany, CRMCompanyFilters, CRMCompanyListResponse, CRMCompanyPayload, CRMCompanyResponse } from '@core/models/crm-company.model';
export type { CRMCompany, CRMCompanyFilters, CRMCompanyListResponse, CRMCompanyPayload, CRMCompanyResponse } from '@core/models/crm-company.model';


/** CRM Company model */

/**
 * Service for managing CRM companies.
 * Preserved verbatim from source — no business logic changes.
 */
@Injectable({ providedIn: 'root' })
export class CRMCompanyService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/companies`;

  /** List companies with filters and pagination. */
  list(params: CRMCompanyFilters = {}): Observable<CRMCompanyListResponse> {
    let httpParams = new HttpParams();
    httpParams = this.appendTrimmedString(httpParams, 'search', params.search);
    httpParams = this.appendBoolean(httpParams, 'is_active', params.is_active);
    httpParams = this.appendNumber(httpParams, 'per_page', params.per_page);
    httpParams = this.appendNumber(httpParams, 'page', params.page);
    httpParams = this.appendTrimmedString(httpParams, 'sort_by', params.sort_by);
    httpParams = this.appendTrimmedString(httpParams, 'sort_dir', params.sort_dir);
    return this.http.get<CRMCompanyListResponse>(this.baseUrl, { params: httpParams });
  }

  /** List all companies without pagination. */
  all(): Observable<CRMCompany[]> {
    return this.http
      .get<{ data: CRMCompany[] } | CRMCompany[]>(`${this.baseUrl}/all`)
      .pipe(map((resp) => ('data' in resp ? resp.data : resp)));
  }

  /** Get a single company by ID. */
  get(id: string): Observable<CRMCompanyResponse> {
    return this.http.get<CRMCompanyResponse>(`${this.baseUrl}/${id}`);
  }

  /** Create a new company. */
  create(payload: CRMCompanyPayload): Observable<CRMCompanyResponse> {
    return this.http.post<CRMCompanyResponse>(this.baseUrl, payload);
  }

  /** Update an existing company. */
  update(id: string, payload: CRMCompanyPayload): Observable<CRMCompanyResponse> {
    return this.http.put<CRMCompanyResponse>(`${this.baseUrl}/${id}`, payload);
  }

  /** Delete a company. */
  delete(id: string): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${id}`);
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
