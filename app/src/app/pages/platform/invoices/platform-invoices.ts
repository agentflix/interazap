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
import {
  NonNullableFormBuilder,
  ReactiveFormsModule,
  Validators,
  FormControl,
} from '@angular/forms';
import { finalize, merge } from 'rxjs';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfBadgeComponent,
  AfButtonComponent,
  AfConfirmModalComponent,
  AfCurrencyInputComponent,
  AfDataTableComponent,
  AfDrawerComponent,
  AfEmptyStateComponent,
  AfIconButtonComponent,
  AfLoadingButtonComponent,
  AfModalComponent,
  AfPageTitleComponent,
  AfPaginationComponent,
  AfSearchInputComponent,
  AfSelectInputComponent,
  AfScrollAreaComponent,
  AfSkeletonTableRowComponent,
  AfStatusBadgeComponent,
  AfTextInputComponent,
  type AfSelectOption,
} from '@shared/components';
import {
  type PlatformBillingInvoice,
  type PlatformBillingInvoiceCreatePayload,
  PlatformBillingInvoiceService,
} from '@core/services/platform-billing-invoice.service';
import { CompanyService } from '@core/services/company.service';
import type { Company } from '@core/models/company.model';
import { PlatformPlanService, type PlatformPlan } from '@platform/services/platform-plan.service';
import { ToastService } from '@core/services/toast.service';

/**
 * Página de faturas da plataforma — listagem, criação, cancelamento, baixa manual e download de faturas.
 */
@Component({
  selector: 'app-platform-invoices',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    DatePipe,
    LucideAngularModule,
    AfDataTableComponent,
    AfBadgeComponent,
    AfButtonComponent,
    AfLoadingButtonComponent,
    AfIconButtonComponent,
    AfSelectInputComponent,
    AfTextInputComponent,
    AfCurrencyInputComponent,
    AfModalComponent,
    AfConfirmModalComponent,
    AfAlertComponent,
    AfDrawerComponent,
    AfEmptyStateComponent,
    AfPageTitleComponent,
    AfPaginationComponent,
    AfSearchInputComponent,
    AfSkeletonTableRowComponent,
    AfScrollAreaComponent,
    AfStatusBadgeComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './platform-invoices.html',
})
export class PlatformInvoices implements OnInit {
  private readonly invoiceService = inject(PlatformBillingInvoiceService);
  private readonly companyService = inject(CompanyService);
  private readonly planService = inject(PlatformPlanService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(NonNullableFormBuilder);

  // ── State ──
  readonly invoices = signal<PlatformBillingInvoice[]>([]);
  readonly isLoading = signal(true);
  readonly hasError = signal(false);
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });

  readonly companies = signal<Company[]>([]);
  readonly plans = signal<PlatformPlan[]>([]);

  readonly isFilterOpen = signal(false);
  readonly isCreateModalOpen = signal(false);
  readonly isCreating = signal(false);

  readonly isCancelModalOpen = signal(false);
  readonly isCancelling = signal(false);
  readonly invoiceToCancel = signal<PlatformBillingInvoice | null>(null);

  readonly isDetailsOpen = signal(false);
  readonly selectedInvoiceDetails = signal<PlatformBillingInvoice | null>(null);

  readonly isMarkPaidModalOpen = signal(false);
  readonly isMarkingPaid = signal(false);
  readonly invoiceToMarkPaid = signal<PlatformBillingInvoice | null>(null);
  readonly downloadingInvoiceId = signal<string | null>(null);

  // ── Filter controls ──
  readonly searchControl = new FormControl('', { nonNullable: true });
  readonly tenantFilterControl = this.fb.control('');
  readonly statusFilterControl = this.fb.control('');
  readonly paymentMethodFilterControl = this.fb.control('');
  readonly dueDateFromControl = this.fb.control('');
  readonly dueDateToControl = this.fb.control('');

  // ── Create form controls ──
  readonly createTenantControl = this.fb.control('', Validators.required);
  readonly createPlanControl = this.fb.control('');
  readonly createReferenceMonthControl = this.fb.control('', [
    Validators.required,
    Validators.pattern(/^\d{4}-(0[1-9]|1[0-2])$/),
  ]);
  readonly createAmountControl = this.fb.control(0, [Validators.required, Validators.min(0)]);
  readonly createDueDateControl = this.fb.control('', Validators.required);
  readonly createStatusControl = this.fb.control('draft');

  // ── Options ──
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

  readonly createStatusOptions: AfSelectOption[] = [
    { value: 'draft', label: 'Rascunho' },
    { value: 'pending', label: 'Pendente' },
  ];

  readonly tenantOptions = computed<AfSelectOption[]>(() => [
    { value: '', label: 'Todas as empresas' },
    ...this.companies().map((c) => ({
      value: String(c.id),
      label: c.name,
    })),
  ]);

  readonly planOptions = computed<AfSelectOption[]>(() => [
    { value: '', label: 'Nenhum' },
    ...this.plans().map((p) => ({
      value: p.id,
      label: p.name,
    })),
  ]);

  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.invoices().length === 0,
  );

  isCreateFormValid(): boolean {
    return (
      this.createTenantControl.value.trim() !== '' &&
      this.createReferenceMonthControl.value.trim() !== '' &&
      this.createAmountControl.value > 0 &&
      this.createDueDateControl.value.trim() !== ''
    );
  }

  readonly cancelMessage = computed(() => {
    const invoice = this.invoiceToCancel();
    if (!invoice) return '';
    const tenantName = invoice.tenant.name || 'Desconhecida';
    return `Tem certeza que deseja cancelar a fatura de ${invoice.reference_month} da empresa ${tenantName}? Esta ação não pode ser desfeita.`;
  });

  readonly markPaidMessage = computed(() => {
    const invoice = this.invoiceToMarkPaid();
    if (!invoice) return '';
    const tenantName = invoice.tenant.name || 'Desconhecida';
    return `Confirmar baixa manual da fatura de ${invoice.reference_month} da empresa ${tenantName}?`;
  });

  private currentPage = 1;
  private currentSearch = '';

  constructor() {
    merge(
      this.tenantFilterControl.valueChanges,
      this.statusFilterControl.valueChanges,
      this.paymentMethodFilterControl.valueChanges,
      this.dueDateFromControl.valueChanges,
      this.dueDateToControl.valueChanges,
    )
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.onFiltersChanged());
  }

  ngOnInit(): void {
    this.fetchInvoices(1);
    this.loadCompanies();
    this.loadPlans();
  }

  // ── Public methods ──

  openFilters(): void {
    this.isFilterOpen.set(true);
  }

  closeFilters(): void {
    this.isFilterOpen.set(false);
  }

  clearFilters(): void {
    this.tenantFilterControl.setValue('');
    this.statusFilterControl.setValue('');
    this.paymentMethodFilterControl.setValue('');
    this.dueDateFromControl.setValue('');
    this.dueDateToControl.setValue('');
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

  openPaymentUrl(invoice: PlatformBillingInvoice): void {
    if (!invoice.payment_url) return;
    window.open(invoice.payment_url, '_blank');
  }

  openDetails(invoice: PlatformBillingInvoice): void {
    this.selectedInvoiceDetails.set(invoice);
    this.isDetailsOpen.set(true);
  }

  closeDetails(): void {
    this.isDetailsOpen.set(false);
  }

  canCancel(invoice: PlatformBillingInvoice): boolean {
    return invoice.status !== 'paid' && invoice.status !== 'cancelled';
  }

  canMarkPaid(invoice: PlatformBillingInvoice): boolean {
    return invoice.status === 'pending' || invoice.status === 'overdue';
  }

  openMarkPaidConfirm(invoice: PlatformBillingInvoice): void {
    if (!this.canMarkPaid(invoice)) return;
    this.invoiceToMarkPaid.set(invoice);
    this.isMarkPaidModalOpen.set(true);
  }

  closeMarkPaidModal(): void {
    this.isMarkPaidModalOpen.set(false);
    this.invoiceToMarkPaid.set(null);
  }

  confirmMarkPaid(): void {
    const invoice = this.invoiceToMarkPaid();
    if (!invoice || this.isMarkingPaid()) return;

    this.isMarkingPaid.set(true);

    this.invoiceService
      .markPaid(invoice.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.toast.success('Fatura marcada como paga.');
          this.isMarkingPaid.set(false);
          this.closeMarkPaidModal();
          this.fetchInvoices(this.currentPage, true);
        },
        error: () => {
          this.toast.error('Erro ao marcar fatura como paga.');
          this.isMarkingPaid.set(false);
          this.closeMarkPaidModal();
        },
      });
  }

  downloadInvoice(invoice: PlatformBillingInvoice): void {
    if (this.downloadingInvoiceId() === invoice.id) return;

    this.downloadingInvoiceId.set(invoice.id);

    this.invoiceService
      .download(invoice.id)
      .pipe(
        finalize(() => this.downloadingInvoiceId.set(null)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (blob) => {
          const pdfBlob = new Blob([blob], { type: 'application/pdf' });
          const url = URL.createObjectURL(pdfBlob);
          const link = document.createElement('a');
          link.href = url;
          link.download = `fatura-${invoice.id.substring(0, 8)}.pdf`;
          link.click();
          URL.revokeObjectURL(url);
        },
        error: () => {
          this.toast.error('Erro ao baixar fatura.');
        },
      });
  }

  // ── Create modal ──

  openCreateModal(): void {
    this.resetCreateForm();
    this.isCreateModalOpen.set(true);
  }

  closeCreateModal(): void {
    this.isCreateModalOpen.set(false);
  }

  submitCreate(): void {
    if (!this.isCreateFormValid() || this.isCreating()) return;

    this.isCreating.set(true);

    const payload: PlatformBillingInvoiceCreatePayload = {
      tenant_id: this.createTenantControl.value,
      plan_id: this.createPlanControl.value || null,
      reference_month: this.createReferenceMonthControl.value,
      amount: this.createAmountControl.value,
      due_date: this.createDueDateControl.value,
      status: (this.createStatusControl.value as 'draft' | 'pending') || 'draft',
    };

    this.invoiceService
      .create(payload)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.toast.success('Fatura criada com sucesso!');
          this.isCreating.set(false);
          this.closeCreateModal();
          this.fetchInvoices(this.currentPage, true);
        },
        error: () => {
          this.toast.error('Erro ao criar fatura.');
          this.isCreating.set(false);
        },
      });
  }

  // ── Cancel modal ──

  openCancelConfirm(invoice: PlatformBillingInvoice): void {
    if (!this.canCancel(invoice)) return;
    this.invoiceToCancel.set(invoice);
    this.isCancelModalOpen.set(true);
  }

  closeCancelModal(): void {
    this.isCancelModalOpen.set(false);
    this.invoiceToCancel.set(null);
  }

  confirmCancel(): void {
    const invoice = this.invoiceToCancel();
    if (!invoice || this.isCancelling()) return;

    this.isCancelling.set(true);

    this.invoiceService
      .cancel(invoice.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.toast.success('Fatura cancelada com sucesso!');
          this.isCancelling.set(false);
          this.closeCancelModal();
          this.fetchInvoices(this.currentPage, true);
        },
        error: () => {
          this.toast.error('Erro ao cancelar fatura.');
          this.isCancelling.set(false);
          this.closeCancelModal();
        },
      });
  }

  // ── Helpers ──

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

  invoiceStatusBadge(status: string | null | undefined): 'online' | 'offline' | 'warning' | 'error' | 'idle' {
    const badges: Record<string, 'online' | 'offline' | 'warning' | 'error' | 'idle'> = {
      draft: 'idle',
      pending: 'warning',
      paid: 'online',
      overdue: 'error',
      cancelled: 'offline',
    };
    return badges[status ?? ''] ?? 'offline';
  }

  formatPaymentMethod(method: string | null | undefined): string {
    const labels: Record<string, string> = {
      pix: 'PIX',
      credit_card: 'Cartão',
      boleto: 'Boleto',
    };
    return labels[method ?? ''] ?? method ?? '—';
  }

  // ── Private methods ──

  private onFiltersChanged(): void {
    this.fetchInvoices(1);
  }

  private fetchInvoices(page: number, keepData = false): void {
    this.currentPage = page;
    this.hasError.set(false);
    this.isLoading.set(true);

    this.invoiceService
      .list({
        search: this.currentSearch || undefined,
        page,
        per_page: this.meta().per_page,
        tenant_id: this.tenantFilterControl.value || undefined,
        status: this.statusFilterControl.value || undefined,
        payment_method: this.paymentMethodFilterControl.value || undefined,
        due_date_from: this.dueDateFromControl.value || undefined,
        due_date_to: this.dueDateToControl.value || undefined,
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

  private loadCompanies(): void {
    this.companyService
      .list({ per_page: 200, page: 1, sort_by: 'name', sort_dir: 'asc' })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => this.companies.set(response.data ?? []),
        error: () => this.toast.error('Não foi possível carregar as empresas.'),
      });
  }

  private loadPlans(): void {
    this.planService
      .list({ per_page: 200, page: 1 })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => this.plans.set(response.data ?? []),
        error: () => this.toast.error('Não foi possível carregar os planos.'),
      });
  }

  private resetCreateForm(): void {
    this.createTenantControl.reset('');
    this.createPlanControl.reset('');
    this.createReferenceMonthControl.reset('');
    this.createAmountControl.reset(0);
    this.createDueDateControl.reset('');
    this.createStatusControl.reset('draft');
  }
}
