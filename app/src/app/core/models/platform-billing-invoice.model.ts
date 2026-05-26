import type { BillingInvoice } from '@core/models/billing-invoice.model';

/**
 * Representa uma fatura de cobrança no contexto administrativo da plataforma.
 *
 * Contexto: estende BillingInvoice com dados do tenant, usado no painel
 * administrativo para visualização e gestão de faturas de todos os tenants.
 */
export interface PlatformBillingInvoice extends BillingInvoice {
  /** Dados resumidos do tenant ao qual a fatura pertence. */
  tenant: {
    id: string;
    name: string;
    tenant_code: string | null;
  };
}

/**
 * Filtros para listagem administrativa de faturas da plataforma.
 */
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

/**
 * Payload para criação manual de fatura no painel administrativo.
 *
 * Contexto: usado por administradores da plataforma para criar
 * faturas manualmente para tenants específicos.
 */
export interface PlatformBillingInvoiceCreatePayload {
  tenant_id: string;
  plan_id: string | null;
  /** Mês de referência no formato YYYY-MM. */
  reference_month: string;
  /** Valor da fatura em reais. */
  amount: number;
  /** Data de vencimento da fatura. */
  due_date: string;
  /** Status inicial da fatura. */
  status?: 'draft' | 'pending';
}

/**
 * Resposta paginada da API para listagem administrativa de faturas.
 */
export interface PlatformBillingInvoicePaginatedResponse {
  data: PlatformBillingInvoice[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}
