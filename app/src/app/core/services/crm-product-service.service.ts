import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';

/** Product/Service model */
export interface ProductService {
  id: string;
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
  track_stock?: boolean;
  stock?: number;
  image?: string;
  attributes?: Record<string, unknown>;
  category?: { id: string; name: string };
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
  success: boolean;
  data: ProductService[];
  meta: {
    current_page: number;
    from: number;
    last_page: number;
    per_page: number;
    to: number;
    total: number;
  };
}

export interface ProductServiceResponse {
  success: boolean;
  data: ProductService;
}

/**
 * Service for managing CRM products and services.
 * Preserved verbatim from source — no business logic changes.
 */
@Injectable({ providedIn: 'root' })
export class ProductServiceService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/products`;

  /** List products/services with filters. */
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

  /** Show a single product/service by ID. */
  show(id: string): Observable<ProductServiceResponse> {
    return this.http.get<ProductServiceResponse>(`${this.baseUrl}/${id}`);
  }

  /** Create a new product/service. */
  create(data: Partial<ProductService>): Observable<ProductServiceResponse> {
    return this.http.post<ProductServiceResponse>(this.baseUrl, data);
  }

  /** Update an existing product/service. */
  update(id: string, data: Partial<ProductService>): Observable<ProductServiceResponse> {
    return this.http.put<ProductServiceResponse>(`${this.baseUrl}/${id}`, data);
  }

  /** Delete a product/service. */
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
