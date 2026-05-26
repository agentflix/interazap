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
 * Gerencia motivos de perda do CRM com operações de CRUD e reordenação.
 */
@Injectable({ providedIn: 'root' })
export class ReasonLossService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/reason-losses`;

  /**
   * Lista motivos de perda com filtros e paginação.
   * @param filters Filtros: search, is_active, paginação, ordenação
   * @returns Observable com lista paginada de motivos de perda
   */
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

  /**
   * Retorna um motivo de perda pelo ID.
   * @param id Identificador do motivo de perda
   * @returns Observable com dados do motivo de perda
   */
  show(id: string): Observable<ReasonLossResponse> {
    return this.http.get<ReasonLossResponse>(`${this.baseUrl}/${id}`);
  }

  /**
   * Cria um novo motivo de perda no CRM.
   * @param data Dados do motivo de perda (nome, descrição, ordem)
   * @returns Observable com o motivo de perda criado
   */
  create(data: Partial<ReasonLoss>): Observable<ReasonLossResponse> {
    return this.http.post<ReasonLossResponse>(this.baseUrl, data);
  }

  /**
   * Atualiza um motivo de perda existente.
   * @param id Identificador do motivo de perda
   * @param data Dados atualizados do motivo de perda
   * @returns Observable com o motivo de perda atualizado
   */
  update(id: string, data: Partial<ReasonLoss>): Observable<ReasonLossResponse> {
    return this.http.put<ReasonLossResponse>(`${this.baseUrl}/${id}`, data);
  }

  /**
   * Exclui um motivo de perda do CRM.
   * @param id Identificador do motivo de perda
   * @returns Observable que completa após a exclusão
   */
  delete(id: string): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${id}`);
  }

  /**
   * Reordena os motivos de perda fornecendo a lista de IDs na ordem desejada.
   * @param order Array de IDs na nova ordem de exibição
   * @returns Observable que completa após a reordenação
   */
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
