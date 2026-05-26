import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import type { ReasonLoss, ReasonLossFilters, ReasonLossItemResponse, ReasonLossListResponse } from '@core/models/reason-loss.model';
export type { ReasonLoss, ReasonLossFilters, ReasonLossItemResponse, ReasonLossListResponse } from '@core/models/reason-loss.model';



/**
 * Gerencia os motivos de perda do CRM (lost deal reasons).
 *
 * Contexto: service HTTP para CRUD de entradas usadas para classificar
 * por que negociações ou deals foram perdidos, incluindo suporte a reordenação.
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
   * Lista motivos de perda com filtros opcionais e paginação.
   *
   * @param filters - Filtros opcionais: active, search, sort, paginação
   * @returns Observable com resposta de lista paginada
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
   * Retorna todos os motivos de perda sem paginação.
   *
   * @returns Observable com array de todos os motivos de perda
   */
  all(): Observable<{ data: ReasonLoss[] }> {
    return this.http.get<{ data: ReasonLoss[] }>(`${this.baseUrl}/all`);
  }

  /**
   * Retorna um motivo de perda pelo ID.
   *
   * @param id - Identificador do motivo de perda
   * @returns Observable com resposta do motivo único
   */
  show(id: string | number): Observable<ReasonLossItemResponse> {
    return this.http.get<ReasonLossItemResponse>(`${this.baseUrl}/${id}`);
  }

  /**
   * Cria um novo motivo de perda.
   *
   * @param data - Dados parciais do motivo de perda
   * @returns Observable com o motivo de perda criado
   */
  create(data: Partial<ReasonLoss>): Observable<ReasonLossItemResponse> {
    return this.http.post<ReasonLossItemResponse>(this.baseUrl, data);
  }

  /**
   * Atualiza um motivo de perda existente.
   *
   * @param id - Identificador do motivo de perda
   * @param data - Dados parciais para atualização
   * @returns Observable com o motivo de perda atualizado
   */
  update(id: string | number, data: Partial<ReasonLoss>): Observable<ReasonLossItemResponse> {
    return this.http.put<ReasonLossItemResponse>(`${this.baseUrl}/${id}`, data);
  }

  /**
   * Exclui um motivo de perda.
   *
   * @param id - Identificador do motivo de perda
   * @returns Observable que completa após a exclusão
   */
  delete(id: string | number): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${id}`);
  }

  /**
   * Reordena motivos de perda fornecendo um array ordenado de IDs.
   *
   * @param order - Array de IDs de motivos de perda na ordem desejada
   * @returns Observable que completa após a reordenação
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
