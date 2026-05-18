export interface BillingLockoutInvoice {
  id: string;
  reference_month: string;
  amount: number;
  due_date: string;
  payment_url?: string;
}

export interface BillingLockoutData {
  error: string;
  message: string;
  billing_status: string;
  locked_at: string | null;
  overdue_invoices: BillingLockoutInvoice[];
  purge_deadline: string | null;
}
