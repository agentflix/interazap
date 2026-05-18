import type { PaginatedResponse } from '@core/models/pagination.model';

/**
 * CRM Product/Service model.
 *
 * @remarks
 * This model is shared between crm-product-service.service and product-service.service.
 * Note: product-service.service defines a slightly different ProductServiceListResponse
 * (without success flag and without from/to in meta) and ProductService.id as string|number.
 * For now, crm-product-service.service uses this canonical model; product-service.service
 * keeps its own inline definitions until the two services are unified.
 */
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

export interface ProductServiceListResponse extends PaginatedResponse<ProductService> {
  success: boolean;
}

export interface ProductServiceResponse {
  success: boolean;
  data: ProductService;
}
