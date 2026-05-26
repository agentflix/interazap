import type { PaginatedResponse } from '@core/models/pagination.model';

/**
 * Representa um departamento da empresa.
 *
 * Contexto: usado no módulo de Configurações para organizar agentes em
 * departamentos e no roteamento de tickets do chat.
 */
export interface Department {
  id: string;
  name: string;
  description?: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

/**
 * Resposta da API para um único departamento.
 */
export interface DepartmentResponse {
  success: boolean;
  message?: string;
  data: Department;
}

/**
 * Resposta paginada da API para listagem de departamentos.
 */
export interface DepartmentListResponse extends PaginatedResponse<Department> {
  success: boolean;
}

/**
 * Filtros para listagem de departamentos.
 */
export interface DepartmentFilters {
  search?: string;
  is_active?: boolean;
  per_page?: number;
  page?: number;
  sort_by?: string;
  sort_dir?: string;
}
