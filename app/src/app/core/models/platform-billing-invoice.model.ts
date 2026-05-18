import type { BillingInvoice } from '@core/models/billing-invoice.model';

export interface PlatformBillingInvoice extends BillingInvoice {
  tenant: {
    id: string;
    name: string;
    tenant_code: string | null;
  };
}

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

export interface PlatformBillingInvoiceCreatePayload {
  tenant_id: string;
  plan_id: string | null;
  reference_month: string;
  amount: number;
  due_date: string;
  status?: 'draft' | 'pending';
}

export interface PlatformBillingInvoicePaginatedResponse {
  data: PlatformBillingInvoice[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}
