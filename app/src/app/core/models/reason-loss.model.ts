import type { PaginatedResponse } from '@core/models/pagination.model';

/** Reason Loss model */
export interface ReasonLoss {
  id: string;
  company_id: string;
  name: string;
  description?: string;
  is_active: boolean;
  requires_comment: boolean;
  position?: number;
  usage_count?: number;
  created_at: string;
  updated_at: string;
}

export interface ReasonLossFilters {
  search?: string;
  active?: boolean;
  is_active?: boolean;
  per_page?: number;
  page?: number;
  sort_by?: string;
  sort_dir?: string;
}

export interface ReasonLossListResponse extends PaginatedResponse<ReasonLoss> {
  success: boolean;
}

export interface ReasonLossResponse {
  success: boolean;
  data: ReasonLoss;
}

export interface ReasonLossItemResponse {
  data: ReasonLoss;
}
