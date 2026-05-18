import type { PaginatedResponse } from '@core/models/pagination.model';

export interface Tag {
  id: string;
  name: string;
  color: string;
  category?: string;
  company_id?: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface TagResponse {
  success: boolean;
  message?: string;
  data: Tag;
}

export interface TagListResponse extends PaginatedResponse<Tag> {
  success: boolean;
}

export interface TagFilters {
  search?: string;
  is_active?: boolean;
  per_page?: number;
  page?: number;
  sort_by?: string;
  sort_dir?: string;
}
