import { type TemplateRef } from '@angular/core';
import { type Observable } from 'rxjs';

/** Sort direction accepted by list endpoints */
export type SortDirection = 'asc' | 'desc';

/** Current sort state for a listing table */
export interface SortState {
  field: string;
  dir: SortDirection;
}

export interface CrudListParams {
  page: number;
  per_page: number;
  sort_by?: string;
  sort_dir?: SortDirection;
}

export interface CrudPaginatedMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface CrudPaginatedResponse<T> {
  data: T[];
  meta: CrudPaginatedMeta;
}

export interface CrudItemResponse<T> {
  data: T;
}

export interface CrudService<T> {
  list: (params: CrudListParams) => Observable<CrudPaginatedResponse<T>>;
  find?: (id: string | number) => Observable<CrudItemResponse<T>>;
  create?: (data: Partial<T>) => Observable<CrudItemResponse<T>>;
  update?: (id: string | number, data: Partial<T>) => Observable<CrudItemResponse<T>>;
  delete?: (id: string | number) => Observable<void>;
}

export interface CrudColumnDef {
  field: string;
  label: string;
  type?: string;
  template?: TemplateRef<{ $implicit: unknown }>;
}

export interface CrudActionDef {
  type: string;
  icon?: string;
  label: string;
  variant?: string;
}

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
