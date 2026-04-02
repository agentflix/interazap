import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';

export interface ProductService {
  id: string | number;
  company_id: string;
  category_id?: string;
  code?: string;
  name: string;
  description?: string;
  type: 'product' | 'service';
  price?: number;
  cost?: number;
  unit?: string;
  stock_quantity?: number;
  min_stock?: number;
  is_active: boolean;
  is_featured?: boolean;
  image?: string;
  attributes?: Record<string, unknown>;
  category?: {
    id: string;
    name: string;
  };
  created_at: string;
  updated_at: string;
}

export interface ProductServiceFilters {
  search?: string;
  type?: 'product' | 'service';
  is_active?: boolean;
  category_id?: string;
  sort_by?: string;
  sort_dir?: string;
  per_page?: number;
  page?: number;
}

export interface ProductServiceListResponse {
  data: ProductService[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

/**
 * Servico responsavel pelo CRUD de produtos e servicos no CRM.
 * Unifica a gestao de itens do tipo 'product' e 'service'.
 *
 * @class ProductServiceService
 * @example
 * ```ts
 * const svc = inject(ProductServiceService);
 * svc.list({ type: 'product', is_active: true }).subscribe();
 * ```
 */
@Injectable({ providedIn: 'root' })
export class ProductServiceService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/products`;

  /**
   * Lista produtos/servicos com suporte a filtros e paginacao.
   *
   * @param filters - Filtros: search, type, is_active, category_id, sort_by, sort_dir, per_page, page
   * @returns Observable com lista paginada de produtos/servicos
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
   * Lista todos os produtos/servicos sem paginacao (endpoint /all).
   *
   * @param type - Opcionalmente filtra por 'product' ou 'service'
   * @returns Observable com array de produtos/servicos
   */
  all(type?: 'product' | 'service'): Observable<{ data: ProductService[] }> {
    let params = new HttpParams();
    params = this.appendType(params, type);
    return this.http.get<{ data: ProductService[] }>(`${environment.apiUrl}/crm/products-all`, {
      params,
    });
  }

  /**
   * Obtem detalhes de um produto/servico especifico.
   *
   * @param id - Identificador do produto/servico
   * @returns Observable com dados do item
   */
  show(id: string | number): Observable<{ data: ProductService }> {
    return this.http.get<{ data: ProductService }>(`${this.baseUrl}/${id}`);
  }

  /**
   * Cria um novo produto ou servico.
   *
   * @param data - Dados do produto/servico
   * @returns Observable com o item criado
   */
  create(data: Partial<ProductService>): Observable<{ data: ProductService }> {
    return this.http.post<{ data: ProductService }>(this.baseUrl, data);
  }

  /**
   * Atualiza um produto/servico existente.
   *
   * @param id - Identificador do item
   * @param data - Dados a serem atualizados
   * @returns Observable com o item atualizado
   */
  update(id: string | number, data: Partial<ProductService>): Observable<{ data: ProductService }> {
    return this.http.put<{ data: ProductService }>(`${this.baseUrl}/${id}`, data);
  }

  /**
   * Remove um produto/servico.
   *
   * @param id - Identificador do item
   * @returns Observable vazio indicando sucesso
   */
  delete(id: string | number): Observable<null> {
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
