import { type TemplateRef } from '@angular/core';
import { type Observable } from 'rxjs';

/**
 * Tipos e interfaces compartilhados para componentes CRUD.
 *
 * Usados pelo AfCrudPageComponent e componentes relacionados para
 * padronizar listagens, paginação e operações de dados.
 */

/** Direção de ordenação aceita pelos endpoints de listagem. */
export type SortDirection = 'asc' | 'desc';

/** Estado de ordenação atual de uma tabela de listagem. */
export interface SortState {
  field: string;
  dir: SortDirection;
}

/** Parâmetros de requisição para listagens paginadas. */
export interface CrudListParams {
  page: number;
  per_page: number;
  sort_by?: string;
  sort_dir?: SortDirection;
}

/** Metadados de paginação retornados pela API. */
export interface CrudPaginatedMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

/** Resposta paginada da API com dados e metadados. */
export interface CrudPaginatedResponse<T> {
  data: T[];
  meta: CrudPaginatedMeta;
}

/** Resposta da API para um único item. */
export interface CrudItemResponse<T> {
  data: T;
}

/** Contrato de serviço CRUD com operações opcionais de criação, atualização e exclusão. */
export interface CrudService<T> {
  list: (params: CrudListParams) => Observable<CrudPaginatedResponse<T>>;
  find?: (id: string | number) => Observable<CrudItemResponse<T>>;
  create?: (data: Partial<T>) => Observable<CrudItemResponse<T>>;
  update?: (id: string | number, data: Partial<T>) => Observable<CrudItemResponse<T>>;
  delete?: (id: string | number) => Observable<void>;
}

/** Definição de coluna para tabelas CRUD. */
export interface CrudColumnDef {
  field: string;
  label: string;
  type?: string;
  template?: TemplateRef<{ $implicit: unknown }>;
}

/** Definição de ação disponível em linhas da tabela CRUD. */
export interface CrudActionDef {
  type: string;
  icon?: string;
  label: string;
  variant?: string;
}

/** Configuração geral do componente CRUD. */
export interface CrudConfig {
  labels?: {
    singular?: string;
    plural?: string;
    createButton?: string;
    modalCreate?: string;
    modalEdit?: string;
  };
  subtitle?: string;
  searchPlaceholder?: string;
  emptyState?: {
    icon?: string;
    title?: string;
    description?: string;
  };
  enableSelection?: boolean;
  enableSearch?: boolean;
  enableFilters?: boolean;
  enableBulkDelete?: boolean;
}
