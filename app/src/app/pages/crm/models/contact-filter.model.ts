import type { SortDirection } from '@shared/components';

/**
 * Valores suportados para filtro de status na listagem de contatos.
 */
export type ContactStatusFilter = 'all' | 'active' | 'inactive';

/**
 * Estado de entrada necessário para construir filtros para o endpoint de contatos.
 */
export interface ContactFilterState {
  /** Termo de busca */
  searchTerm: string;
  /** Página atual */
  page: number;
  /** Campo de ordenação */
  sortBy: string;
  /** Direção da ordenação */
  sortDir: SortDirection;
  /** Filtro de status */
  status: ContactStatusFilter;
}
