/**
 * Metadata for paginated API responses.
 * Contains information about the current page, total pages, items per page, and total count.
 *
 * @example
 * ```typescript
 * const meta: PaginationMeta = {
 *   current_page: 1,
 *   last_page: 5,
 *   per_page: 20,
 *   total: 100
 * };
 * ```
 */
export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

/**
 * Generic paginated response wrapper.
 * Combines the array of data items with pagination metadata.
 *
 * @example
 * ```typescript
 * const response: PaginatedResponse<Contact> = {
 *   data: [contact1, contact2, contact3],
 *   meta: { current_page: 1, last_page: 3, per_page: 20, total: 55 }
 * };
 * ```
 */
export interface PaginatedResponse<T> {
  data: T[];
  meta: PaginationMeta;
}
