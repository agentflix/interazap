import { type HttpResponse, HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import { type TenantDetails } from '@shared/models/tenant-details.model';
import { type PaginatedResponse } from '@core/models/pagination.model';
import type { Company, CompanyFilters } from '@core/models/company.model';

/**
 * Gerencia operações CRUD e auxiliares de tenants (empresas) da plataforma.
 *
 * @see `/platform/tenants` — endpoint base da API
 */
@Injectable({ providedIn: 'root' })
export class CompanyService {
  private readonly baseUrl = `${environment.apiUrl}/platform/tenants`;
  private readonly http = inject(HttpClient);

  /** Lista tenants (empresas) com filtros opcionais e paginação. */
  list(filters: CompanyFilters = {}): Observable<PaginatedResponse<Company>> {
    const params = this.buildListParams(filters);

    return this.http.get<PaginatedResponse<Company>>(this.baseUrl, { params });
  }

  /** Cria um novo tenant (empresa) na plataforma. */
  create(data: Partial<Company>): Observable<{ data: Company }> {
    return this.http.post<{ data: Company }>(this.baseUrl, data);
  }

  /** Retorna um tenant pelo ID. */
  find(id: string | number): Observable<{ data: Company }> {
    return this.http.get<{ data: Company }>(`${this.baseUrl}/${id}`);
  }

  /** Atualiza dados de um tenant existente. */
  update(id: string | number, data: Partial<Company>): Observable<{ data: Company }> {
    return this.http.put<{ data: Company }>(`${this.baseUrl}/${id}`, data);
  }

  /** Remove (soft-delete) um tenant pelo ID. */
  delete(id: string | number): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${id}`);
  }

  /** Alterna o status ativo/inativo de um tenant. */
  toggleActive(id: string | number): Observable<{ data: Company }> {
    return this.http.patch<{ data: Company }>(`${this.baseUrl}/${id}/toggle-active`, {});
  }

  /** Restaura um tenant previamente excluído (soft-delete). */
  restore(id: string | number): Observable<{ data: Company }> {
    return this.http.post<{ data: Company }>(`${this.baseUrl}/${id}/restore`, {});
  }

  /** Exclui permanentemente um tenant (hard-delete). */
  forceDelete(id: string | number): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${id}/force`);
  }

  /** Exclui permanentemente um tenant com confirmação por senha (purge). */
  purge(id: string | number, password: string): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${id}/purge`, {
      body: { password },
    });
  }

  /**
   * Retorna informações detalhadas do tenant: dados da empresa, plano contratado e uso de recursos.
   */
  details(id: string | number): Observable<{ data: TenantDetails }> {
    return this.http.get<{ data: TenantDetails }>(`${this.baseUrl}/${id}/details`);
  }

  /** Exporta lista de tenants como arquivo binário (CSV/Excel) com filtros. */
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
