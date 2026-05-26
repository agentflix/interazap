import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { type Observable, map } from 'rxjs';
import { environment } from '@env/environment';
import type { CRMCompany, CRMCompanyFilters, CRMCompanyListResponse, CRMCompanyPayload, CRMCompanyResponse } from '@core/models/crm-company.model';
export type { CRMCompany, CRMCompanyFilters, CRMCompanyListResponse, CRMCompanyPayload, CRMCompanyResponse } from '@core/models/crm-company.model';


/**
 * Gerencia empresas do CRM com operações de CRUD e listagem completa.
 */
@Injectable({ providedIn: 'root' })
export class CRMCompanyService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/companies`;

  /**
   * Lista empresas com filtros e paginação.
   * @param params Filtros opcionais: search, is_active, paginação, ordenação
   * @returns Observable com lista paginada de empresas do CRM
   */
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

  /**
   * Lista todas as empresas sem paginação (para selects/dropdowns).
   * @returns Observable com array completo de empresas
   */
  all(): Observable<CRMCompany[]> {
    return this.http
      .get<{ data: CRMCompany[] } | CRMCompany[]>(`${this.baseUrl}/all`)
      .pipe(map((resp) => ('data' in resp ? resp.data : resp)));
  }

  /**
   * Retorna uma empresa pelo ID.
   * @param id Identificador da empresa
   * @returns Observable com dados da empresa
   */
  get(id: string): Observable<CRMCompanyResponse> {
    return this.http.get<CRMCompanyResponse>(`${this.baseUrl}/${id}`);
  }

  /**
   * Cria uma nova empresa no CRM.
   * @param payload Dados da empresa (nome, documento, endereço, etc.)
   * @returns Observable com a empresa criada
   */
  create(payload: CRMCompanyPayload): Observable<CRMCompanyResponse> {
    return this.http.post<CRMCompanyResponse>(this.baseUrl, payload);
  }

  /**
   * Atualiza dados de uma empresa existente.
   * @param id Identificador da empresa
   * @param payload Dados atualizados da empresa
   * @returns Observable com a empresa atualizada
   */
  update(id: string, payload: CRMCompanyPayload): Observable<CRMCompanyResponse> {
    return this.http.put<CRMCompanyResponse>(`${this.baseUrl}/${id}`, payload);
  }

  /**
   * Exclui uma empresa do CRM.
   * @param id Identificador da empresa
   * @returns Observable que completa após a exclusão
   */
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
