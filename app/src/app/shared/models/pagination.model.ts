/**
 * Extended pagination metadata with optional range indicators.
 */
export interface PaginationMeta {
  /** Current page number (1-based) */
  current_page: number;
  /** Item number at the start of the current page (optional) */
  from?: number;
  /** Last available page number */
  last_page: number;
  /** Number of items per page */
  per_page: number;
  /** Item number at the end of the current page (optional) */
  to?: number;
  /** Total number of items across all pages */
  total: number;
}

/**
 * Standard paginated API response with optional success flag.
 *
 * @example
 * ```typescript
 * const response: PaginatedResponse<Contact> = {
 *   success: true,
 *   data: contacts,
 *   meta: { current_page: 1, last_page: 3, per_page: 15, total: 42 }
 * };
 * ```
 */
export interface PaginatedResponse<T> {
  /** Indicates whether the request was successful */
  success?: boolean;
  /** Array of items for the current page */
  data: T[];
  /** Pagination metadata */
  meta: PaginationMeta;
}
