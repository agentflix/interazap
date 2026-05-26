import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import type {
  ProductService,
  ProductServiceFilters,
  ProductServiceListResponse,
  ProductServiceResponse,
} from '@core/models/product-service.model';

/**
 * Gerencia produtos e serviços do CRM com operações de CRUD e filtragem.
 */
@Injectable({ providedIn: 'root' })
export class ProductServiceService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/products`;

  /**
   * Lista produtos/serviços com filtros e paginação.
   * @param filters Filtros: search, type, is_active, categoria, paginação, ordenação
   * @returns Observable com lista paginada de produtos/serviços
   */
  list(filters: ProductServiceFilters = {}): Observable<ProductServiceListResponse> {
    let params = new HttpParams();
    params = this.appendTrimmedString(params, 'search', filters.search);
    params = this.appendType(params, filters.type);
    params = this.appendBoolean(params, 'is_active', filters.is_active);
    params = this.appendTrimmedString(params, 'category_id', filters.category_id);
    params = this.appendTrimmedString(params, 'sort_by', filters.sort_by);
    params = this.appendTrimmedString(params, 'sort_dir', filters.sort_dir);
    params = this.appendNumber(params, 'per_page', filters.per_page);
    params = this.appendNumber(params, 'page', filters.page);
    return this.http.get<ProductServiceListResponse>(this.baseUrl, { params });
  }

  /**
   * Retorna um produto/serviço pelo ID.
   * @param id Identificador do produto/serviço
   * @returns Observable com dados do produto/serviço
   */
  show(id: string): Observable<ProductServiceResponse> {
    return this.http.get<ProductServiceResponse>(`${this.baseUrl}/${id}`);
  }

  /**
   * Cria um novo produto/serviço no CRM.
   * @param data Dados do produto/serviço (nome, preço, tipo, categoria)
   * @returns Observable com o produto/serviço criado
   */
  create(data: Partial<ProductService>): Observable<ProductServiceResponse> {
    return this.http.post<ProductServiceResponse>(this.baseUrl, data);
  }

  /**
   * Atualiza um produto/serviço existente.
   * @param id Identificador do produto/serviço
   * @param data Dados atualizados do produto/serviço
   * @returns Observable com o produto/serviço atualizado
   */
  update(id: string, data: Partial<ProductService>): Observable<ProductServiceResponse> {
    return this.http.put<ProductServiceResponse>(`${this.baseUrl}/${id}`, data);
  }

  /**
   * Exclui um produto/serviço do CRM.
   * @param id Identificador do produto/serviço
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

  private appendType(params: HttpParams, value?: 'product' | 'service'): HttpParams {
    if (value === undefined) return params;
    return params.set('type', value);
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
