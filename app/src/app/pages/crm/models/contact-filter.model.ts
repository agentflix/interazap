import type { SortDirection } from '@shared/components';

/**
 * Supported status filter values for contacts list.
 */
export type ContactStatusFilter = 'all' | 'active' | 'inactive';

/**
 * Input state required to build list filters for contacts endpoint.
 */
export interface ContactFilterState {
  searchTerm: string;
  page: number;
  sortBy: string;
  sortDir: SortDirection;
  status: ContactStatusFilter;
}
