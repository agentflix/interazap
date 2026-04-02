import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';

export interface BillingInvoice {
  id: string;
  tenant_id: string;
  plan_id?: string | null;
  reference_month: string;
  amount: number;
  status: string;
  status_label?: string;
  status_color?: string;
  due_date: string;
  paid_at?: string | null;
  payment_method?: string | null;
  payment_url?: string | null;
  asaas_payment_id?: string | null;
  has_pix?: boolean;
  plan?: {
    id: string;
    name: string;
  };
  created_at: string;
  updated_at: string;
}

export interface BillingInvoiceFilters {
  search?: string;
  status?: string;
  reference_month?: string;
  due_date_from?: string;
  due_date_to?: string;
  payment_method?: string;
  tenant_id?: string;
  per_page?: number;
  page?: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface InvoiceResponse {
  data: BillingInvoice;
}

export interface PixResponse {
  data: {
    payload: string;
    qr_code: string;
  };
}

export type BillingInvoicePaymentMethod = 'PIX' | 'CREDIT_CARD';

export interface BillingInvoicePaymentResponse {
  data: {
    method: BillingInvoicePaymentMethod;
    invoice: BillingInvoice;
    qr_code_base64?: string | null;
    pix_copy_paste?: string | null;
    expires_at?: string | null;
    status?: string | null;
  };
}

export interface BillingInvoiceReceiptResponse {
  data: {
    invoice_id: string;
    reference_month: string;
    amount: number;
    paid_at: string | null;
    payment_method: string | null;
    tenant: {
      id: string;
      name: string;
      email?: string | null;
    };
    plan?: {
      id: string;
      name: string;
    } | null;
  };
}

/**
 * Service for tenant-facing billing invoice operations.
 *
 * @remarks
 * Handles invoice listing, viewing, creation, and payment (PIX, credit card).
 */
@Injectable({ providedIn: 'root' })
export class BillingInvoiceService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/billing/invoices`;

  /**
   * Lists invoices with optional filters and pagination.
   *
   * @param filters - Filter criteria (search, status, dates, etc.)
   * @returns Observable with paginated invoice response
   */
  list(filters: BillingInvoiceFilters = {}): Observable<PaginatedResponse<BillingInvoice>> {
    let params = new HttpParams();

    if (filters.search) params = params.set('search', filters.search);
    if (filters.status) params = params.set('status', filters.status);
    if (filters.reference_month) params = params.set('reference_month', filters.reference_month);
    if (filters.due_date_from) params = params.set('due_date_from', filters.due_date_from);
    if (filters.due_date_to) params = params.set('due_date_to', filters.due_date_to);
    if (filters.payment_method) params = params.set('payment_method', filters.payment_method);
    if (filters.tenant_id) params = params.set('tenant_id', filters.tenant_id);
    if (filters.per_page) params = params.set('per_page', String(filters.per_page));
    if (filters.page) params = params.set('page', String(filters.page));

    return this.http.get<PaginatedResponse<BillingInvoice>>(this.baseUrl, { params });
  }

  /**
   * Retrieves a single invoice by ID.
   *
   * @param id - Invoice identifier
   * @returns Observable with invoice response
   */
  find(id: string): Observable<InvoiceResponse> {
    return this.http.get<InvoiceResponse>(`${this.baseUrl}/${id}`);
  }

  /**
   * Creates a new invoice.
   *
   * @param payload - Invoice data
   * @returns Observable with created invoice response
   */
  create(payload: Partial<BillingInvoice>): Observable<InvoiceResponse> {
    return this.http.post<InvoiceResponse>(this.baseUrl, payload);
  }

  /**
   * Updates an existing invoice.
   *
   * @param id - Invoice identifier
   * @param payload - Updated invoice data
   * @returns Observable with updated invoice response
   */
  update(id: string, payload: Partial<BillingInvoice>): Observable<InvoiceResponse> {
    return this.http.put<InvoiceResponse>(`${this.baseUrl}/${id}`, payload);
  }

  /**
   * Deletes an invoice.
   *
   * @param id - Invoice identifier
   * @returns Observable completing on deletion
   */
  delete(id: string): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${id}`);
  }

  /**
   * Processes payment for an invoice via PIX or credit card.
   *
   * @param id - Invoice identifier
   * @param payload - Payment method and optional card/holder details
   * @returns Observable with payment response including QR code for PIX
   */
  pay(
    id: string,
    payload: {
      method: BillingInvoicePaymentMethod;
      card?: {
        holder_name: string;
        number: string;
        expiry_month: string;
        expiry_year: string;
        cvv: string;
      };
      holder_info?: {
        name: string;
        email: string;
        cpf_cnpj: string;
        postal_code: string;
        address_number: string;
        phone: string;
      };
    },
  ): Observable<BillingInvoicePaymentResponse> {
    return this.http.post<BillingInvoicePaymentResponse>(`${this.baseUrl}/${id}/pay`, payload);
  }

  /**
   * Retrieves PIX payment data (payload and QR code) for an invoice.
   *
   * @param id - Invoice identifier
   * @returns Observable with PIX payload and QR code
   */
  getPix(id: string): Observable<PixResponse> {
    return this.http.get<PixResponse>(`${this.baseUrl}/${id}/pix`);
  }

  /**
   * Retrieves the payment receipt for an invoice.
   *
   * @param id - Invoice identifier
   * @returns Observable with receipt data
   */
  getReceipt(id: string): Observable<BillingInvoiceReceiptResponse> {
    return this.http.get<BillingInvoiceReceiptResponse>(`${this.baseUrl}/${id}/receipt`);
  }
}
