import { CommonModule } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';
import { Router } from '@angular/router';
import { AfButtonComponent, AfEmptyStateComponent, AfPageTitleComponent } from '@shared/components';
import { formatCurrency } from '@shared/utils/currency';
import { AuthStoreService } from '@core/services/auth-store.service';
import { BillingStatusService } from '@core/services/billing-status.service';

/**
 * Lockout page shown when backend blocks tenant access for delinquency.
 */
@Component({
  selector: 'app-billing-lockout',
  standalone: true,
  imports: [CommonModule, AfPageTitleComponent, AfButtonComponent, AfEmptyStateComponent],
  templateUrl: './lockout.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LockoutPage {
  private readonly router = inject(Router);
  private readonly authStoreService = inject(AuthStoreService);
  private readonly billingStatusService = inject(BillingStatusService);

  readonly lockoutData = this.billingStatusService.lockoutData;
  readonly firstInvoice = computed(() => this.lockoutData()?.overdue_invoices?.[0] ?? null);

  readonly countdownLabel = computed(() => {
    const purgeDeadline = this.lockoutData()?.purge_deadline;
    if (!purgeDeadline) {
      return '-';
    }

    const deadlineDate = new Date(purgeDeadline);
    const now = new Date();
    const diffMs = deadlineDate.getTime() - now.getTime();
    const days = Math.max(0, Math.ceil(diffMs / (1000 * 60 * 60 * 24)));

    return `${days} dia(s)`;
  });

  payNow(): void {
    const invoice = this.firstInvoice();
    if (invoice) {
      void this.router.navigate(['/billing/invoices', invoice.id, 'pay']);
    }
  }

  goToMyPlan(): void {
    void this.router.navigate(['/settings/my-plan']);
  }

  logout(): void {
    this.billingStatusService.clearLockout();
    this.authStoreService.logout();
    void this.router.navigate(['/login']);
  }

  formatCurrency(value: number): string {
    return formatCurrency(value);
  }

  formatDate(value: string): string {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return value;
    }

    return new Intl.DateTimeFormat('pt-BR').format(date);
  }
}

export default LockoutPage;
