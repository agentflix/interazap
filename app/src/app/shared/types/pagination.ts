/**
 * Pagination metadata returned with paginated API responses.
 */
export interface PaginationMeta {
  /** Current page number (1-based) */
  current_page: number;
  /** Last available page number */
  last_page: number;
  /** Number of items per page */
  per_page: number;
  /** Total number of items across all pages */
  total: number;
}

/**
 * Standard paginated response envelope used throughout the application.
 *
 * @example
 * ```typescript
 * const response: Paginated<User> = {
 *   data: users,
 *   meta: { current_page: 1, last_page: 5, per_page: 20, total: 100 }
 * };
 * ```
 */
export interface Paginated<T> {
  /** Array of items for the current page */
  data: T[];
  /** Pagination metadata */
  meta: PaginationMeta;
}
