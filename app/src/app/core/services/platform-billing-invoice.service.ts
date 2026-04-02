import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import { type BillingInvoice } from './billing-invoice.service';

/** Fatura enriquecida com dados do tenant (visão admin) */
export interface PlatformBillingInvoice extends BillingInvoice {
  tenant: {
    id: string;
    name: string;
    tenant_code: string | null;
  };
}

/** Filtros da listagem admin */
export interface PlatformBillingInvoiceFilters {
  page?: number;
  per_page?: number;
  search?: string;
  tenant_id?: string;
  status?: string;
  payment_method?: string;
  due_date_from?: string;
  due_date_to?: string;
}

/** Payload para criação de fatura pela plataforma */
export interface PlatformBillingInvoiceCreatePayload {
  tenant_id: string;
  plan_id: string | null;
  reference_month: string;
  amount: number;
  due_date: string;
  status?: 'draft' | 'pending';
}

/** Resposta paginada da API */
export interface PlatformBillingInvoicePaginatedResponse {
  data: PlatformBillingInvoice[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

/**
 * Service para gerenciamento de faturas na visão de plataforma (admin).
 * Endpoints: GET/POST/DELETE /platform/billing/invoices
 */
@Injectable({ providedIn: 'root' })
export class PlatformBillingInvoiceService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/platform/billing/invoices`;

  /**
   * Lista faturas de todos os tenants com paginação e filtros.
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
   */
  create(
    payload: PlatformBillingInvoiceCreatePayload,
  ): Observable<{ data: PlatformBillingInvoice }> {
    return this.http.post<{ data: PlatformBillingInvoice }>(this.baseUrl, payload);
  }

  /**
   * Cancela (soft-delete) uma fatura.
   */
  cancel(id: string): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${id}`);
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
