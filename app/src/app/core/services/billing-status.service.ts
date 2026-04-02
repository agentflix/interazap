import { Injectable, computed, signal } from '@angular/core';

/**
 * Minimal overdue invoice payload used by the lockout screen.
 */
export interface BillingLockoutInvoice {
  id: string;
  reference_month: string;
  amount: number;
  due_date: string;
  payment_url?: string;
}

/**
 * Lockout response data returned by backend when tenant access is blocked.
 */
export interface BillingLockoutData {
  error: string;
  message: string;
  billing_status: string;
  locked_at: string | null;
  overdue_invoices: BillingLockoutInvoice[];
  purge_deadline: string | null;
}

/**
 * Stores billing lockout state used by interceptor and lockout UI.
 *
 * @remarks
 * Holds the lockout data returned by the API when a tenant has overdue invoices,
 * used to block access and display payment prompts.
 */
@Injectable({ providedIn: 'root' })
export class BillingStatusService {
  private readonly lockoutState = signal<BillingLockoutData | null>(null);

  readonly lockoutData = this.lockoutState.asReadonly();
  readonly isLocked = computed(() => this.lockoutState() !== null);

  /**
   * Sets tenant lockout data from API responses.
   */
  setLocked(data: BillingLockoutData): void {
    this.lockoutState.set(data);
  }

  /**
   * Clears lockout data when user logs out or tenant is unlocked.
   */
  clearLockout(): void {
    this.lockoutState.set(null);
  }
}
