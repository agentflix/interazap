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
