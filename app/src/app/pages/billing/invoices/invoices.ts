import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
} from '@angular/core';
import { DatePipe } from '@angular/common';
import { formatCurrency } from '@shared/utils/currency';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule, FormControl } from '@angular/forms';
import { merge } from 'rxjs';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfBadgeComponent,
  AfButtonComponent,
  AfDataTableComponent,
  AfDrawerComponent,
  AfEmptyStateComponent,
  AfIconButtonComponent,
  AfModalComponent,
  AfPageTitleComponent,
  AfPaginationComponent,
  AfSearchInputComponent,
  AfSelectInputComponent,
  AfSkeletonTableRowComponent,
  AfTextInputComponent,
  type AfSelectOption,
} from '@shared/components';
import { type BillingInvoice, BillingInvoiceService } from '@core/services/billing-invoice.service';
import { ToastService } from '@core/services/toast.service';
import { InvoicePaymentModalComponent } from './components/invoice-payment-modal/invoice-payment-modal';

@Component({
  selector: 'app-billing-invoices',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    DatePipe,
    LucideAngularModule,
    AfDataTableComponent,
    AfBadgeComponent,
    AfButtonComponent,
    AfIconButtonComponent,
    AfSelectInputComponent,
    AfTextInputComponent,
    AfAlertComponent,
    AfDrawerComponent,
    AfEmptyStateComponent,
    AfPageTitleComponent,
    AfPaginationComponent,
    AfSearchInputComponent,
    AfSkeletonTableRowComponent,
    InvoicePaymentModalComponent,
/**
 * Billing invoices page component for the Billing module.
 * @selector app-billing-invoices
 */
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './invoices.html',
})
export class BillingInvoices implements OnInit {
  private readonly billingService = inject(BillingInvoiceService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(NonNullableFormBuilder);

  readonly invoices = signal<BillingInvoice[]>([]);
  readonly isLoading = signal(true);
  readonly hasError = signal(false);
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });

  readonly selectedInvoice = signal<BillingInvoice | null>(null);
  readonly isPayModalOpen = signal(false);
  readonly isFilterOpen = signal(false);

  readonly searchControl = new FormControl('', { nonNullable: true });
  readonly dueDateFromControl = this.fb.control('');
  readonly dueDateToControl = this.fb.control('');
  readonly invoiceStatusControl = this.fb.control('');
  readonly paymentMethodFilterControl = this.fb.control('');

  readonly statusOptions: AfSelectOption[] = [
    { value: '', label: 'Todos' },
    { value: 'draft', label: 'Rascunho' },
    { value: 'pending', label: 'Pendente' },
    { value: 'paid', label: 'Pago' },
    { value: 'overdue', label: 'Atrasado' },
    { value: 'cancelled', label: 'Cancelado' },
  ];

  readonly paymentMethodOptions: AfSelectOption[] = [
    { value: '', label: 'Todos' },
    { value: 'pix', label: 'PIX' },
    { value: 'credit_card', label: 'Cartão' },
  ];

  readonly activeFilters = computed(() => ({
    dueDateFrom: this.dueDateFromControl.value?.trim() || '',
    dueDateTo: this.dueDateToControl.value?.trim() || '',
    status: this.invoiceStatusControl.value?.trim() || '',
    paymentMethod: this.paymentMethodFilterControl.value?.trim() || '',
  }));

  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.invoices().length === 0,
  );

  private currentPage = 1;
  private currentSearch = '';

  constructor() {
    merge(
      this.dueDateFromControl.valueChanges,
      this.dueDateToControl.valueChanges,
      this.invoiceStatusControl.valueChanges,
      this.paymentMethodFilterControl.valueChanges,
    )
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.onFiltersChanged());
  }

  ngOnInit(): void {
    this.fetchInvoices(1);
  }

  openFilters(): void {
    this.isFilterOpen.set(true);
  }

  closeFilters(): void {
    this.isFilterOpen.set(false);
  }

  clearFilters(): void {
    this.dueDateFromControl.setValue('');
    this.dueDateToControl.setValue('');
    this.invoiceStatusControl.setValue('');
    this.paymentMethodFilterControl.setValue('');
    this.searchControl.setValue('');
  }

  applyFilters(): void {
    this.currentSearch = this.searchControl.value.trim();
    this.fetchInvoices(1);
    this.closeFilters();
  }

  onSearch(term: string): void {
    this.currentSearch = term.trim();
    this.fetchInvoices(1);
  }

  loadPage(page: number): void {
    this.fetchInvoices(page);
  }

  refresh(): void {
    this.fetchInvoices(this.currentPage, true);
  }

  retry(): void {
    this.fetchInvoices(this.currentPage, true);
  }

  canPay(invoice: BillingInvoice): boolean {
    return ['draft', 'pending', 'overdue'].includes(invoice.status);
  }

  openPay(invoice: BillingInvoice): void {
    this.selectedInvoice.set(invoice);
    this.isPayModalOpen.set(true);
  }

  closePay(): void {
    this.isPayModalOpen.set(false);
    this.selectedInvoice.set(null);
  }

  onPaymentSuccess(): void {
    this.toast.success('Cobrança gerada com sucesso!');
    this.fetchInvoices(this.currentPage, true);
    this.closePay();
  }

  openPaymentUrl(invoice: BillingInvoice): void {
    if (!invoice.payment_url) return;
    window.open(invoice.payment_url, '_blank');
  }

  statusVariant(color?: string): 'default' | 'success' | 'warning' | 'danger' | 'info' {
    const palette: Record<string, 'default' | 'success' | 'warning' | 'danger' | 'info'> = {
      success: 'success',
      primary: 'info',
      danger: 'danger',
      warning: 'warning',
      default: 'default',
    };

    return palette[color ?? 'default'] ?? 'default';
  }

  formatCurrency(value: number): string {
    return formatCurrency(value);
  }

  private onFiltersChanged(): void {
    if (this.currentPage !== 1) {
      this.fetchInvoices(1);
      return;
    }

    this.fetchInvoices(this.currentPage);
  }

  private fetchInvoices(page: number, keepData = false): void {
    this.currentPage = page;
    this.hasError.set(false);
    this.isLoading.set(true);

    this.billingService
      .list({
        search: this.currentSearch || undefined,
        page,
        per_page: this.meta().per_page,
        status: this.activeFilters().status || undefined,
        payment_method: this.activeFilters().paymentMethod || undefined,
        due_date_from: this.activeFilters().dueDateFrom || undefined,
        due_date_to: this.activeFilters().dueDateTo || undefined,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.invoices.set(response.data ?? []);
          this.meta.set(response.meta);
          this.hasError.set(false);
          this.isLoading.set(false);
        },
        error: () => {
          if (!keepData) {
            this.invoices.set([]);
          }
          this.hasError.set(true);
          this.isLoading.set(false);
        },
      });
  }
}

export default BillingInvoices;
