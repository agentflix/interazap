/**
 * Supported response item types from global search API.
 */
export type GlobalSearchType = 'contact' | 'company' | 'negotiation' | 'ticket' | 'user';

/**
 * Search result item rendered in spotlight list.
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
 * Grouped result payload for one entity type.
 */
export interface GlobalSearchGroup {
  total: number;
  items: GlobalSearchItem[];
}

/**
 * Metadata payload from global search API.
 */
export interface GlobalSearchMeta {
  query: string;
  total: number;
  per_type: number;
  duration_ms: number;
}

/**
 * Complete global search response grouped by backend keys.
 */
export interface GlobalSearchResponse {
  data: Partial<
    Record<'contacts' | 'companies' | 'negotiations' | 'tickets' | 'users', GlobalSearchGroup>
  >;
  meta: GlobalSearchMeta;
}

/**
 * Recent search entry persisted in localStorage.
 */
export interface RecentSearch {
  label: string;
  type: GlobalSearchType;
  id: string;
  url: string;
  timestamp: number;
}
