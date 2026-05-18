import type { PaginatedResponse } from '@core/models/pagination.model';

/** Funnel step model */
export interface FunnelStep {
  id: string | number;
  funnel_id?: string | number;
  crm_negotiation_funnel_id?: string | number;
  name: string;
  order: number;
  color?: string | null;
  is_active?: boolean;
  created_at?: string;
  updated_at?: string;
}

/** Funnel model */
export interface Funnel {
  id: string | number;
  name: string;
  description?: string;
  is_active: boolean;
  is_default?: boolean;
  steps?: FunnelStep[];
  steps_count?: number;
  created_at?: string;
  updated_at?: string;
}

export interface FunnelFilters {
  search?: string;
  is_active?: boolean | string;
  per_page?: number;
  page?: number;
  sort_by?: string;
  sort_dir?: string;
}

export interface FunnelListResponse extends PaginatedResponse<Funnel> {
  success: boolean;
}

export interface FunnelResponse {
  success: boolean;
  data: Funnel;
}

export interface FunnelPayload {
  name: string;
  description?: string;
  is_active?: boolean;
  steps?: FunnelStepPayload[];
}

export interface FunnelStepPayload {
  name: string;
  order?: number;
  color?: string;
  is_active?: boolean;
}
