import type { PaginatedResponse } from '@core/models/pagination.model';

/**
 * Representa uma etiqueta (tag) para categorização de contatos e tickets.
 *
 * Contexto: usado no módulo de Configurações para gerenciar etiquetas
 * e no CRM/Chat para categorizar contatos, tickets e negociações.
 */
export interface Tag {
  id: string;
  name: string;
  /** Cor da etiqueta em formato hexadecimal (ex: #FF5733). */
  color: string;
  /** Categoria agrupadora da etiqueta. */
  category?: string;
  company_id?: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

/**
 * Resposta da API para uma única etiqueta.
 */
export interface TagResponse {
  success: boolean;
  message?: string;
  data: Tag;
}

/**
 * Resposta paginada da API para listagem de etiquetas.
 */
export interface TagListResponse extends PaginatedResponse<Tag> {
  success: boolean;
}

/**
 * Filtros para listagem de etiquetas.
 */
export interface TagFilters {
  search?: string;
  is_active?: boolean;
  per_page?: number;
  page?: number;
  sort_by?: string;
  sort_dir?: string;
}
