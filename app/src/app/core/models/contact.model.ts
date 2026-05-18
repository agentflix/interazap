import type { PaginatedResponse } from '@core/models/pagination.model';

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
 *   crm_company_id: '456'
 * };
 * ```
 */
export interface Contact {
  /** Unique identifier for the contact */
  id: string | number;
  /** Full name of the contact */
  name: string;
  /** Phone number */
  phone?: string;
  /** WhatsApp number */
  whatsapp?: string;
  /** Email address */
  email?: string;
  /** URL to the contact's avatar image (legacy alias) */
  avatar?: string;
  /** URL to the contact's avatar image */
  avatar_url?: string | null;
  /** Jabber ID for messaging */
  jid?: string;
  /** Line ID for messaging */
  lid?: string;
  /** Tax identification document (CPF/CNPJ) */
  document?: string;
  /** Source where the contact originated from */
  source?: string;
  /** Whether the contact is currently active */
  is_active: boolean;
  /** ID of the associated company (platform tenant) */
  company_id?: string;
  /** ID of the associated CRM company */
  crm_company_id?: string;
  /** Associated company details */
  company?: {
    id: string;
    name: string;
    document?: string;
  };
  /** Dynamic key-value pair custom fields */
  custom_fields?: Record<string, unknown>;
  /** Internal notes about the contact */
  notes?: string;
  /** Timestamp of the last contact made */
  last_contact_at?: string;
  /** Tags associated with the contact */
  tags?: { id: string; name: string }[] | string[];
  /** Number of calls made to/from this contact */
  calleds_count?: number;
  /** Creation timestamp */
  created_at: string;
  /** Last update timestamp */
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
  crm_company_id?: string;
  sort_by?: string;
  sort_dir?: 'asc' | 'desc';
  per_page?: number;
  page?: number;
}

/**
 * Response structure for paginated contact list.
 */
export interface ContactListResponse extends PaginatedResponse<Contact> {
  /** Success flag */
  success: boolean;
}

/**
 * Response structure for a single contact.
 */
export interface ContactResponse {
  /** Success flag */
  success: boolean;
  /** Message from the API */
  message: string;
  /** Contact data wrapper */
  data: Contact;
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
