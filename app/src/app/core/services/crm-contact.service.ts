import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import type { PaginatedResponse } from '@shared/models/pagination.model';

/**
 * CRM Contact model interface.
 * Represents a contact person or entity in the CRM system.
 */
export interface Contact {
  /** Unique identifier for the contact */
  id: string;
  /** Full name of the contact */
  name: string;
  /** Phone number */
  phone?: string;
  /** WhatsApp number */
  whatsapp?: string;
  /** Email address */
  email?: string;
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
  /** ID of the associated company */
  company_id: string;
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
 * Filters for listing contacts.
 */
export interface ContactFilters {
  /** Search term for name or other fields */
  search?: string;
  /** Filter by active status */
  is_active?: boolean;
  /** Filter by tag name */
  tag?: string;
  /** Field name to sort by */
  sort_by?: string;
  /** Sorting direction */
  sort_dir?: 'asc' | 'desc';
  /** Number of items per page */
  per_page?: number;
  /** Page number to retrieve */
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
 * Response structure for contact import upload.
 */
export interface ContactImportUploadResponse {
  /** Upload data details */
  data: {
    /** Unique identifier for the import process */
    import_id: string;
    /** CSV headers found in the file */
    headers: string[];
    /** Sample rows from the CSV file */
    sample: string[][];
    /** Identified CSV delimiter */
    delimiter: ',' | ';';
    /** Whether the file has a header row */
    has_header: boolean;
  };
}

/**
 * Summary of a contact import process results.
 */
export interface ContactImportSummary {
  /** Total processed rows */
  processed: number;
  /** Number of successfully imported contacts */
  imported: number;
  /** Number of contacts skipped (e.g., duplicates) */
  skipped: number;
  /** Number of failed imports */
  failed: number;
  /** Error details per line */
  errors?: { line?: number; message: string }[];
  /** Whether the import is queued for background processing */
  queued?: boolean;
  /** Total rows in the import file */
  rows?: number;
}

/**
 * Response structure for contact import execution.
 */
export interface ContactImportResponse {
  /** Import summary data */
  data: ContactImportSummary;
}

/**
 * Service for managing CRM contacts.
 * Provides methods for CRUD operations, import/export, and status management.
 * Preserved verbatim from source — no business logic changes.
 */
@Injectable({ providedIn: 'root' })
export class ContactService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/contacts`;

  /**
   * Retrieves a paginated list of contacts based on provided filters.
   * @param filters - Optional filtering and pagination parameters.
   * @returns Observable of ContactListResponse.
   */
  list(filters: ContactFilters = {}): Observable<ContactListResponse> {
    let params = new HttpParams();
    params = this.appendTrimmedString(params, 'search', filters.search);
    params = this.appendBoolean(params, 'is_active', filters.is_active);
    params = this.appendTrimmedString(params, 'tag', filters.tag);
    params = this.appendTrimmedString(params, 'sort_by', filters.sort_by);
    params = this.appendSortDir(params, filters.sort_dir);
    params = this.appendNumber(params, 'per_page', filters.per_page);
    params = this.appendNumber(params, 'page', filters.page);
    return this.http.get<ContactListResponse>(this.baseUrl, { params });
  }

  /**
   * Retrieves a single contact by its ID.
   * @param id - Contact identifier.
   * @returns Observable of ContactResponse.
   */
  find(id: string): Observable<ContactResponse> {
    return this.http.get<ContactResponse>(`${this.baseUrl}/${id}`);
  }

  /**
   * Creates a new contact in the system.
   * @param data - Partial contact data for creation.
   * @returns Observable of ContactResponse.
   */
  create(data: Partial<Contact>): Observable<ContactResponse> {
    return this.http.post<ContactResponse>(this.baseUrl, data);
  }

  /**
   * Updates an existing contact.
   * @param id - Contact identifier.
   * @param data - Partial contact data for update.
   * @returns Observable of ContactResponse.
   */
  update(id: string, data: Partial<Contact>): Observable<ContactResponse> {
    return this.http.put<ContactResponse>(`${this.baseUrl}/${id}`, data);
  }

  /**
   * Deletes a contact from the system.
   * @param id - Contact identifier.
   * @returns Observable of null on success.
   */
  delete(id: string): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${id}`);
  }

  /**
   * Toggles the active/inactive status of a contact.
   * @param id - Contact identifier.
   * @returns Observable of updated ContactResponse.
   */
  toggleActive(id: string): Observable<ContactResponse> {
    return this.http.patch<ContactResponse>(`${this.baseUrl}/${id}/toggle-active`, {});
  }

  /**
   * Exports contacts to a CSV file.
   * @param filters - Optional filters to apply to the export.
   * @returns Observable of a Blob containing the CSV data.
   */
  export(filters: ContactFilters = {}): Observable<Blob> {
    let params = new HttpParams();
    params = this.appendTrimmedString(params, 'search', filters.search);
    params = this.appendBoolean(params, 'is_active', filters.is_active);
    params = this.appendTrimmedString(params, 'tag', filters.tag);
    return this.http.get(`${this.baseUrl}/export`, { params, responseType: 'blob' });
  }

  /**
   * Uploads a CSV file to be prepared for import.
   * @param file - The CSV file to upload.
   * @returns Observable of ContactImportUploadResponse with mapping information.
   */
  uploadImport(file: File): Observable<ContactImportUploadResponse> {
    const formData = new FormData();
    formData.append('file', file);
    return this.http.post<ContactImportUploadResponse>(`${this.baseUrl}/import/upload`, formData);
  }

  /**
   * Finalizes the contact import process with field mapping.
   * @param payload - Import details including mapping and delimiter.
   * @returns Observable of ContactImportResponse.
   */
  importContacts(payload: {
    import_id: string;
    mapping: { name: string; number: string; email?: string; company?: string };
    delimiter?: ',' | ';';
    has_header?: boolean;
  }): Observable<ContactImportResponse> {
    return this.http.post<ContactImportResponse>(`${this.baseUrl}/import`, payload);
  }

  /**
   * Helper to append a trimmed string to HttpParams if it exists and is not empty.
   * @private
   */
  private appendTrimmedString(params: HttpParams, key: string, value?: string): HttpParams {
    if (value === undefined) return params;
    const trimmed = value.trim();
    if (trimmed.length === 0) return params;
    return params.set(key, trimmed);
  }

  /**
   * Helper to append a boolean value to HttpParams.
   * @private
   */
  private appendBoolean(params: HttpParams, key: string, value?: boolean): HttpParams {
    if (value === undefined) return params;
    return params.set(key, String(value));
  }

  /**
   * Helper to append sort direction to HttpParams.
   * @private
   */
  private appendSortDir(params: HttpParams, value?: 'asc' | 'desc'): HttpParams {
    if (value === undefined) return params;
    return params.set('sort_dir', value);
  }

  /**
   * Helper to append a number to HttpParams.
   * @private
   */
  private appendNumber(params: HttpParams, key: string, value?: number): HttpParams {
    if (value === undefined) return params;
    return params.set(key, String(value));
  }
}
