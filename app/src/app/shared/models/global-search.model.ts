/**
 * Tipos de itens retornados pela API de busca global.
 */
export type GlobalSearchType = 'contact' | 'company' | 'negotiation' | 'ticket' | 'user';

/**
 * Item de resultado de busca renderizado na lista do spotlight.
 */
export interface GlobalSearchItem {
  id: string;
  type: GlobalSearchType;
  label: string;
  sublabel?: string;
  meta?: string;
  url: string;
  icon: string;
}

/**
 * Payload de resultado agrupado para um tipo de entidade.
 */
export interface GlobalSearchGroup {
  total: number;
  items: GlobalSearchItem[];
}

/**
 * Payload de metadados da API de busca global.
 */
export interface GlobalSearchMeta {
  query: string;
  total: number;
  per_type: number;
  duration_ms: number;
}

/**
 * Resposta completa da busca global agrupada por chaves do backend.
 */
export interface GlobalSearchResponse {
  data: Partial<
    Record<'contacts' | 'companies' | 'negotiations' | 'tickets' | 'users', GlobalSearchGroup>
  >;
  meta: GlobalSearchMeta;
}

/**
 * Entrada de busca recente persistida no localStorage.
 */
export interface RecentSearch {
  label: string;
  type: GlobalSearchType;
  id: string;
  url: string;
  timestamp: number;
}
