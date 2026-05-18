import type { PaginatedResponse } from '@core/models/pagination.model';

export interface Department {
  id: string;
  name: string;
  description?: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface DepartmentResponse {
  success: boolean;
  message?: string;
  data: Department;
}

export interface DepartmentListResponse extends PaginatedResponse<Department> {
  success: boolean;
}

export interface DepartmentFilters {
  search?: string;
  is_active?: boolean;
  per_page?: number;
  page?: number;
  sort_by?: string;
  sort_dir?: string;
}
