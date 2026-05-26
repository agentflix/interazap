import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import { type BillingInvoice } from './billing-invoice.service';
import type { PlatformBillingInvoice, PlatformBillingInvoiceCreatePayload, PlatformBillingInvoiceFilters, PlatformBillingInvoicePaginatedResponse } from '@core/models/platform-billing-invoice.model';
export type { PlatformBillingInvoice, PlatformBillingInvoiceCreatePayload, PlatformBillingInvoiceFilters, PlatformBillingInvoicePaginatedResponse } from '@core/models/platform-billing-invoice.model';


/**
 * Serviço para gerenciamento de faturas na visão de plataforma (admin).
 *
 * Contexto: service HTTP com endpoints em /platform/billing/invoices para
 * listagem paginada, criação, cancelamento, marcação como pago e download de faturas.
 */
@Injectable({ providedIn: 'root' })
export class PlatformBillingInvoiceService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/platform/billing/invoices`;

  /**
   * Lista faturas de todos os tenants com paginação e filtros.
   *
   * @param filters - Filtros: search, tenant_id, status, payment_method, due_date_from, due_date_to, per_page, page
   * @returns Observable com resposta paginada de faturas
   */
  list(
    filters: PlatformBillingInvoiceFilters = {},
  ): Observable<PlatformBillingInvoicePaginatedResponse> {
    let params = new HttpParams();

    params = this.appendTrimmedString(params, 'search', filters.search);
    params = this.appendTrimmedString(params, 'tenant_id', filters.tenant_id);
    params = this.appendTrimmedString(params, 'status', filters.status);
    params = this.appendTrimmedString(params, 'payment_method', filters.payment_method);
    params = this.appendTrimmedString(params, 'due_date_from', filters.due_date_from);
    params = this.appendTrimmedString(params, 'due_date_to', filters.due_date_to);
    params = this.appendNumber(params, 'per_page', filters.per_page);
    params = this.appendNumber(params, 'page', filters.page);

    return this.http.get<PlatformBillingInvoicePaginatedResponse>(this.baseUrl, { params });
  }

  /**
   * Cria uma nova fatura para um tenant específico.
   *
   * @param payload - Dados da fatura: tenant_id, amount, due_date, etc.
   * @returns Observable com a fatura criada
   */
  create(
    payload: PlatformBillingInvoiceCreatePayload,
  ): Observable<{ data: PlatformBillingInvoice }> {
    return this.http.post<{ data: PlatformBillingInvoice }>(this.baseUrl, payload);
  }

  /**
   * Cancela (soft-delete) uma fatura.
   *
   * @param id - Identificador da fatura
   * @returns Observable que completa após o cancelamento
   */
  cancel(id: string): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${id}`);
  }

  /**
   * Marca uma fatura como paga.
   *
   * @param id - Identificador da fatura
   * @returns Observable com a fatura atualizada
   */
  markPaid(id: string): Observable<{ data: PlatformBillingInvoice }> {
    return this.http.patch<{ data: PlatformBillingInvoice }>(
      `${this.baseUrl}/${id}/mark-paid`,
      {},
    );
  }

  /**
   * Baixa o PDF de uma fatura.
   *
   * @param id - Identificador da fatura
   * @returns Observable com o blob do arquivo PDF
   */
  download(id: string): Observable<Blob> {
    return this.http.get(`${this.baseUrl}/${id}/download`, {
      responseType: 'blob',
    });
  }

  private appendTrimmedString(params: HttpParams, key: string, value?: string): HttpParams {
    if (value === undefined) return params;
    const trimmed = value.trim();
    if (trimmed.length === 0) return params;
    return params.set(key, trimmed);
  }

  private appendNumber(params: HttpParams, key: string, value?: number): HttpParams {
    if (value === undefined) return params;
    return params.set(key, String(value));
  }
}
