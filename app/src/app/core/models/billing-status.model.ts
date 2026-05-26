/**
 * Representa uma fatura em atraso exibida no bloqueio de acesso por inadimplência.
 *
 * Contexto: usado pelo interceptor de billing para exibir informações
 * sobre faturas vencidas quando o tenant está bloqueado.
 */
export interface BillingLockoutInvoice {
  id: string;
  /** Mês de referência da fatura no formato YYYY-MM. */
  reference_month: string;
  /** Valor da fatura em reais. */
  amount: number;
  /** Data de vencimento da fatura. */
  due_date: string;
  /** URL de pagamento gerada pelo gateway (ex: Asaas). */
  payment_url?: string;
}

/**
 * Dados retornados pela API quando o tenant está bloqueado por inadimplência.
 *
 * Contexto: retornado com status HTTP 402 pelo backend quando o tenant
 * possui faturas vencidas e o plano está em modo de bloqueio.
 */
export interface BillingLockoutData {
  /** Código de erro. */
  error: string;
  /** Mensagem descritiva do bloqueio. */
  message: string;
  /** Status atual do billing (ex: blocked, overdue). */
  billing_status: string;
  /** Data e hora em que o bloqueio foi aplicado, ou null se ainda não bloqueado. */
  locked_at: string | null;
  /** Lista de faturas vencidas que causaram o bloqueio. */
  overdue_invoices: BillingLockoutInvoice[];
  /** Prazo para purga dos dados caso a inadimplência não seja resolvida. */
  purge_deadline: string | null;
}
