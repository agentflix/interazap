/**
 * Represents a CRM contact entity.
 *
 * @example
 * ```typescript
 * const contact: Contact = {
 *   id: '123',
 *   name: 'John Doe',
 *   email: 'john@example.com',
 *   is_active: true,
 *   company_id: '456'
 * };
 * ```
 */
export interface Contact {
  id: string | number;
  name: string;
  phone?: string;
  whatsapp?: string;
  email?: string;
  avatar?: string;
  jid?: string;
  lid?: string;
  document?: string;
  source?: string;
  is_active: boolean;
  company_id: string;
  crm_company_id?: string;
  company?: {
    id: string;
    name: string;
    document?: string;
  };
  custom_fields?: Record<string, unknown>;
  notes?: string;
  last_contact_at?: string;
  tags?: { id: string; name: string }[] | string[];
  calleds_count?: number;
  created_at: string;
  updated_at: string;
}

/**
 * Filter parameters for querying contacts list.
 *
 * @example
 * ```typescript
 * const filters: ContactFilters = {
 *   search: 'John',
 *   is_active: true,
 *   tag: 'vip',
 *   per_page: 20
 * };
 * ```
 */
export interface ContactFilters {
  search?: string;
  is_active?: boolean;
  tag?: string;
  sort_by?: string;
  sort_dir?: 'asc' | 'desc';
  per_page?: number;
  page?: number;
}

/**
 * Response from uploading a CSV file for contact import.
 * Contains metadata about the file structure for preview before confirmation.
 *
 * @example
 * ```typescript
 * const response: ContactImportUploadResponse = {
 *   data: {
 *     import_id: 'uuid-here',
 *     headers: ['name', 'email', 'phone'],
 *     sample: [['John', 'john@example.com', '+5511999999999']],
 *     delimiter: ',',
 *     has_header: true
 *   }
 * };
 * ```
 */
export interface ContactImportUploadResponse {
  data: {
    import_id: string;
    headers: string[];
    sample: string[][];
    delimiter: ',' | ';';
    has_header: boolean;
  };
}

/**
 * Summary result of a contact import operation.
 * Contains counts of processed, imported, skipped, and failed records.
 *
 * @example
 * ```typescript
 * const summary: ContactImportSummary = {
 *   processed: 100,
 *   imported: 95,
 *   skipped: 3,
 *   failed: 2,
 *   errors: [{ line: 5, message: 'Invalid email format' }]
 * };
 * ```
 */
export interface ContactImportSummary {
  processed: number;
  imported: number;
  skipped: number;
  failed: number;
  errors?: { line?: number; message: string }[];
  queued?: boolean;
  rows?: number;
}

/**
 * Response returned after a contact import operation completes.
 * Wraps the import summary with a standard response envelope.
 *
 * @example
 * ```typescript
 * const response: ContactImportResponse = {
 *   data: { processed: 50, imported: 48, skipped: 1, failed: 1 }
 * };
 * ```
 */
export interface ContactImportResponse {
  data: ContactImportSummary;
}
