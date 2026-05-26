/**
 * Representa uma fatura de cobrança do tenant.
 *
 * Contexto: utilizado no módulo de Faturas (billing/invoices) para exibir
 * e gerenciar cobranças mensais do plano do tenant.
 */
export interface BillingInvoice {
  id: string;
  tenant_id: string;
  /** ID do plano ao qual a fatura se refere. */
  plan_id?: string | null;
  /** Mês de referência da fatura no formato YYYY-MM. */
  reference_month: string;
  /** Valor total da fatura em reais. */
  amount: number;
  /** Status da fatura (ex: pending, paid, overdue). */
  status: string;
  /** Rótulo legível do status para exibição. */
  status_label?: string;
  /** Cor associada ao status para uso na interface. */
  status_color?: string;
  /** Data de vencimento da fatura. */
  due_date: string;
  /** Data de pagamento efetivo, ou null se ainda não pago. */
  paid_at?: string | null;
  /** Método de pagamento utilizado. */
  payment_method?: string | null;
  /** URL da página de pagamento gerada pelo gateway. */
  payment_url?: string | null;
  /** ID do pagamento no sistema Asaas. */
  asaas_payment_id?: string | null;
  /** Indica se a fatura possui opção de pagamento via PIX. */
  has_pix?: boolean;
  plan?: {
    id: string;
    name: string;
  };
  created_at: string;
  updated_at: string;
}

/**
 * Filtros para listagem de faturas de cobrança.
 *
 * Contexto: parâmetros aceitos pelo endpoint de listagem de faturas.
 */
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

/**
 * Resposta da API para uma única fatura.
 */
export interface InvoiceResponse {
  data: BillingInvoice;
}

/**
 * Resposta da API com os dados de pagamento PIX.
 */
export interface PixResponse {
  data: {
    /** Payload do PIX copia-e-cola. */
    payload: string;
    /** QR Code em base64 para pagamento via PIX. */
    qr_code: string;
  };
}

/** Método de pagamento aceito pelo sistema de faturas. */
export type BillingInvoicePaymentMethod = 'PIX' | 'CREDIT_CARD';

/**
 * Resposta da API após iniciar o processo de pagamento de uma fatura.
 *
 * Contexto: retornada ao acionar o pagamento de uma fatura pendente.
 */
export interface BillingInvoicePaymentResponse {
  data: {
    method: BillingInvoicePaymentMethod;
    invoice: BillingInvoice;
    /** QR Code em base64 (apenas para PIX). */
    qr_code_base64?: string | null;
    /** Código PIX copia-e-cola (apenas para PIX). */
    pix_copy_paste?: string | null;
    /** Data de expiração do código PIX. */
    expires_at?: string | null;
    status?: string | null;
  };
}

/**
 * Resposta da API com os dados do recibo de pagamento de uma fatura.
 *
 * Contexto: utilizado para exibir comprovante de pagamento ao tenant.
 */
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
