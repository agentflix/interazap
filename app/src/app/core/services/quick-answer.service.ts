import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable, map } from 'rxjs';
import { environment } from '@env/environment';
import { type PaginatedResponse } from '@core/models/pagination.model';
import type { QuickAnswer, QuickAnswerFilters } from '@core/models/quick-answer.model';
export type { QuickAnswer, QuickAnswerFilters } from '@core/models/quick-answer.model';



/**
 * Serviço para gestão de Respostas Rápidas (atalhos de texto).
 *
 * Permite cadastrar, editar e listar modelos de mensagens pré-definidas para
 * agilizar o atendimento humano, incluindo suporte a filtros e normalização de API.
 *
 * @class QuickAnswerService
 * @description Service para CRUD de biblioteca de respostas rápidas.
 */
@Injectable({
  providedIn: 'root',
})
export class QuickAnswerService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/chat/quick-answers`;

  /**
   * Obtém a lista paginada de respostas rápidas, aplicando filtros de busca e status.
   * @param filters Filtros de busca e paginação.
   * @returns {Observable<PaginatedResponse<QuickAnswer>>} Stream finito com dados normalizados.
   */
  list(filters: QuickAnswerFilters = {}): Observable<PaginatedResponse<QuickAnswer>> {
    let params = new HttpParams();

    params = this.appendTrimmedString(params, 'search', filters.search);
    params = this.appendBoolean(params, 'is_active', filters.is_active);
    params = this.appendTrimmedString(params, 'category', filters.category);
    params = this.appendNumber(params, 'page', filters.page);
    params = this.appendNumber(params, 'per_page', filters.per_page);
    params = this.appendTrimmedString(params, 'sort_by', filters.sort_by);
    params = this.appendSortDir(params, filters.sort_dir);

    type ListShape =
      | PaginatedResponse<QuickAnswer>
      | { data: QuickAnswer[]; meta?: PaginatedResponse<QuickAnswer>['meta'] }
      | { data: { data: QuickAnswer[]; meta?: PaginatedResponse<QuickAnswer>['meta'] } };
    return this.http
      .get<ListShape>(this.baseUrl, { params })
      .pipe(map((resp) => this.normalizeList(resp)));
  }

  /**
   * Cria uma nova resposta rápida no biblioteca.
   * @param data Dados da resposta rápida.
   * @returns {Observable<QuickAnswer>} Stream finito com o registro criado.
   */
  create(data: Partial<QuickAnswer>): Observable<QuickAnswer> {
    return this.http.post<{ data: QuickAnswer }>(this.baseUrl, data).pipe(map((r) => r.data));
  }

  /**
   * Atualiza uma resposta rápida existente.
   * @param id Identificador da resposta.
   * @param data Atributos a serem alterados.
   * @returns {Observable<QuickAnswer>} Stream finito com o registro atualizado.
   */
  update(id: string, data: Partial<QuickAnswer>): Observable<QuickAnswer> {
    return this.http
      .put<{ data: QuickAnswer }>(`${this.baseUrl}/${id}`, data)
      .pipe(map((r) => r.data));
  }

  /**
   * Remove uma resposta rápida do sistema.
   * @param id Identificador da resposta.
   * @returns {Observable<void>} Stream finito.
   */
  delete(id: string | number): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${id}`);
  }

  /**
   * Localiza uma resposta rápida específica por ID.
   * @param id Identificador da resposta.
   * @returns {Observable<QuickAnswer>} Stream finito com os dados encontrados.
   */
  find(id: string): Observable<QuickAnswer> {
    return this.http.get<{ data: QuickAnswer }>(`${this.baseUrl}/${id}`).pipe(map((r) => r.data));
  }

  /**
   * Obtém todas as respostas rápidas ativas para uso imediato em menus de seleção.
   * @returns {Observable<QuickAnswer[]>} Stream finito com a lista completa.
   */
  listAllActive(): Observable<QuickAnswer[]> {
    return this.http
      .get<{ data: QuickAnswer[] } | QuickAnswer[]>(`${this.baseUrl}/all`)
      .pipe(map((resp) => ('data' in resp ? resp.data : resp)));
  }

  private normalizeList(
    resp:
      | { data: unknown; meta?: PaginatedResponse<QuickAnswer>['meta'] }
      | PaginatedResponse<QuickAnswer>,
  ): PaginatedResponse<QuickAnswer> {
    const defaultMeta: PaginatedResponse<QuickAnswer>['meta'] = {
      current_page: 1,
      last_page: 1,
      per_page: 0,
      total: 0,
    };

    if ('data' in resp && Array.isArray((resp as { data: unknown }).data)) {
      const data = resp.data as QuickAnswer[];
      return {
        data,
        meta: resp.meta ?? { ...defaultMeta, per_page: data.length, total: data.length },
      };
    }

    const nested = (
      resp as { data?: { data?: unknown; meta?: PaginatedResponse<QuickAnswer>['meta'] } }
    ).data;
    if (nested && Array.isArray(nested.data)) {
      const data = nested.data as QuickAnswer[];
      return {
        data,
        meta: nested.meta ??
          resp.meta ?? { ...defaultMeta, per_page: data.length, total: data.length },
      };
    }

    return { data: [], meta: defaultMeta };
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

  private appendSortDir(params: HttpParams, value?: 'asc' | 'desc'): HttpParams {
    if (value === undefined) return params;
    return params.set('sort_dir', value);
  }
}
