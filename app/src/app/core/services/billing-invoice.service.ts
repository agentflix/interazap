import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import { type PaginatedResponse } from '@core/models/pagination.model';
import type { BillingInvoice, BillingInvoiceFilters, BillingInvoicePaymentMethod, BillingInvoicePaymentResponse, BillingInvoiceReceiptResponse, InvoiceResponse, PixResponse } from '@core/models/billing-invoice.model';
export type { BillingInvoice, BillingInvoiceFilters, BillingInvoicePaymentMethod, BillingInvoicePaymentResponse, BillingInvoiceReceiptResponse, InvoiceResponse, PixResponse } from '@core/models/billing-invoice.model';



/**
 * Operações de faturas de cobrança para o tenant autenticado.
 *
 * Cobre listagem, visualização, criação e pagamento de faturas via PIX ou cartão de crédito.
 */
@Injectable({ providedIn: 'root' })
export class BillingInvoiceService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/billing/invoices`;

  /**
   * Lista faturas com filtros opcionais e paginação.
   *
   * @param filters Critérios de filtro: search, status, datas, método de pagamento, etc.
   * @returns Observable com resposta paginada de faturas
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

  /** Retorna uma fatura específica pelo ID. */
  find(id: string): Observable<InvoiceResponse> {
    return this.http.get<InvoiceResponse>(`${this.baseUrl}/${id}`);
  }

  /** Cria uma nova fatura. */
  create(payload: Partial<BillingInvoice>): Observable<InvoiceResponse> {
    return this.http.post<InvoiceResponse>(this.baseUrl, payload);
  }

  /** Atualiza dados de uma fatura existente. */
  update(id: string, payload: Partial<BillingInvoice>): Observable<InvoiceResponse> {
    return this.http.put<InvoiceResponse>(`${this.baseUrl}/${id}`, payload);
  }

  /** Remove uma fatura pelo ID. */
  delete(id: string): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${id}`);
  }

  /**
   * Processa o pagamento de uma fatura via PIX ou cartão de crédito.
   *
   * @param id ID da fatura
   * @param payload Método de pagamento e dados opcionais de cartão/portador
   * @returns Observable com resposta de pagamento (inclui QR code para PIX)
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

  /** Retorna dados de pagamento PIX (payload e QR code) de uma fatura. */
  getPix(id: string): Observable<PixResponse> {
    return this.http.get<PixResponse>(`${this.baseUrl}/${id}/pix`);
  }

  /** Retorna o comprovante de pagamento de uma fatura. */
  getReceipt(id: string): Observable<BillingInvoiceReceiptResponse> {
    return this.http.get<BillingInvoiceReceiptResponse>(`${this.baseUrl}/${id}/receipt`);
  }
}
