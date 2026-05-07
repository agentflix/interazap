import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
  viewChild,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { forkJoin } from 'rxjs';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfCheckboxInputComponent,
  AfConfirmModalComponent,
  AfCrudPageComponent,
  AfDataTableComponent,
  AfIconButtonComponent,
  AfLoadingButtonComponent,
  AfModalComponent,
  AfPasswordInputComponent,
  AfScrollAreaComponent,
  AfSelectInputComponent,
  AfSortableHeaderComponent,
  AfStatusBadgeComponent,
  AfTableActionsComponent,
  type AfSelectOption,
  type SortDirection,
} from '@shared/components';
import { type Company, CompanyService } from '@core/services/company.service';
import { type TenantDetails } from '@shared/models/tenant-details.model';
import { ToastService } from '@core/services/toast.service';
import { AuthService } from '@core/services/auth.service';
import { AuthStoreService, type AuthUser } from '@core/services/auth-store.service';
import { Router } from '@angular/router';
import { TenantExportComponent } from './components/tenant-export/tenant-export';
import { TenantFormComponent } from './components/tenant-form/tenant-form';

type TenantSortField = 'name' | 'document' | 'is_active' | 'created_at';

@Component({
  selector: 'app-tenants',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    LucideAngularModule,
    AfCrudPageComponent,
    AfModalComponent,
    AfConfirmModalComponent,
    AfButtonComponent,
    AfIconButtonComponent,
    AfCheckboxInputComponent,
    AfSelectInputComponent,
    AfDataTableComponent,
    AfSortableHeaderComponent,
    AfLoadingButtonComponent,
    AfScrollAreaComponent,
    AfStatusBadgeComponent,
    AfTableActionsComponent,
    AfAlertComponent,
    AfPasswordInputComponent,
    TenantExportComponent,
    TenantFormComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './tenants.html',
})
export class Tenants implements OnInit {
  private readonly service = inject(CompanyService);
  private readonly toast = inject(ToastService);
  private readonly authService = inject(AuthService);
  private readonly authStore = inject(AuthStoreService);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);

  readonly isSuperAdmin = computed(() => this.authStore.hasPermission('platform.tenants.manage'));

  readonly tenantFormRef = viewChild<TenantFormComponent>('tenantForm');
  readonly isFormSaving = computed(() => this.tenantFormRef()?.isSaving() ?? false);

  readonly tenants = signal<Company[]>([]);
  readonly isLoading = signal(true);
  readonly hasError = signal(false);
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.tenants().length === 0,
  );

  readonly filterStatusControl = new FormControl<string>('all', { nonNullable: true });
  readonly filterStatusOptions: AfSelectOption[] = [
    { label: 'Todos', value: 'all' },
    { label: 'Ativo', value: 'active' },
    { label: 'Inativo', value: 'inactive' },
    { label: 'Lixeira', value: 'trashed' },
  ];

  readonly selectAllControl = new FormControl<boolean>(false, { nonNullable: true });
  private readonly rowSelectionControls = new Map<string, FormControl<boolean>>();
  readonly selectedTenantIds = signal<string[]>([]);
  readonly selectedCount = computed(() => this.selectedTenantIds().length);
  readonly hasSelection = computed(() => this.selectedCount() > 0);

  readonly showFormModal = signal(false);
  readonly selectedTenant = signal<Company | null>(null);
  readonly formModalTitle = computed(() =>
    this.selectedTenant() ? 'Editar inquilino' : 'Novo inquilino',
  );

  readonly showDeleteModal = signal(false);
  readonly tenantToDelete = signal<Company | null>(null);
  readonly isDeleting = signal(false);
  readonly deleteModalTitle = computed(() =>
    this.tenantToDelete() ? 'Excluir inquilino' : 'Excluir inquilinos',
  );
  readonly deleteConfirmLabel = computed(() =>
    this.tenantToDelete() ? 'Excluir' : 'Excluir selecionados',
  );
  readonly deleteMessage = computed(() => {
    const single = this.tenantToDelete();
    if (single) {
      return `Tem certeza que deseja excluir o inquilino "${single.name}"? Esta ação não pode ser desfeita.`;
    }
    return `Tem certeza que deseja excluir os ${this.selectedCount()} inquilinos selecionados? Esta ação não pode ser desfeita.`;
  });

  readonly showForceDeleteModal = signal(false);
  readonly tenantToForceDelete = signal<Company | null>(null);
  readonly isForceDeleting = signal(false);
  readonly forceDeleteMessage = computed(() => {
    const tenant = this.tenantToForceDelete();
    return tenant
      ? `Tem certeza que deseja excluir permanentemente o inquilino "${tenant.name}"?`
      : 'Tem certeza que deseja excluir permanentemente este inquilino?';
  });

  readonly showImpersonateModal = signal(false);
  readonly tenantToImpersonate = signal<Company | null>(null);
  readonly isImpersonating = signal(false);
  readonly impersonatePasswordControl = new FormControl<string>('', { nonNullable: true });

  readonly adminPasswordControl = new FormControl<string>('', { nonNullable: true });

  // ─── Details panel ─────────────────────────────────────────────────────────
  readonly isDetailsOpen = signal(false);
  readonly isDetailsLoading = signal(false);
  readonly detailsError = signal(false);
  readonly tenantDetails = signal<TenantDetails | null>(null);
  private detailTenantId: string | number | null = null;

  readonly currentSearchTerm = signal('');

  private pageNumber = signal(1);
  readonly sortBy = signal<TenantSortField>('name');
  readonly sortDir = signal<SortDirection>('asc');

  constructor() {
    this.filterStatusControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        this.pageNumber.set(1);
        this.loadTenants();
      });

    this.selectAllControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((checked) => {
        const next = checked ? this.tenants().map((t) => String(t.id)) : [];
        this.selectedTenantIds.set(next);
        for (const tenant of this.tenants()) {
          this.getRowSelectionControl(String(tenant.id)).setValue(checked);
        }
      });
  }

  ngOnInit(): void {
    this.loadTenants();
  }

  openCreate(): void {
    this.selectedTenant.set(null);
    this.showFormModal.set(true);
  }

  openEdit(tenant: Company): void {
    this.selectedTenant.set(tenant);
    this.showFormModal.set(true);
  }

  openDetails(tenant: Company): void {
    this.detailTenantId = tenant.id;
    this.isDetailsOpen.set(true);
    this.loadDetails(tenant.id);
  }

  closeDetails(): void {
    this.isDetailsOpen.set(false);
  }

  retryDetails(): void {
    if (this.detailTenantId !== null) {
      this.loadDetails(this.detailTenantId);
    }
  }

  /**
   * Returns the progress bar fill color class based on usage ratio.
   */
  getProgressColor(current: number, limit: number): string {
    const ratio = limit > 0 ? current / limit : 0;
    if (ratio >= 1) return 'bg-danger';
    if (ratio >= 0.8) return 'bg-warning';
    return 'bg-primary';
  }

  /**
   * Returns the progress bar width percentage capped at 100.
   */
  getProgressWidth(current: number, limit: number): number {
    if (limit <= 0) return 0;
    return Math.min(100, Math.round((current / limit) * 100));
  }

  private loadDetails(id: string | number): void {
    this.isDetailsLoading.set(true);
    this.detailsError.set(false);
    this.tenantDetails.set(null);

    this.service
      .details(id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.tenantDetails.set(response.data);
          this.isDetailsLoading.set(false);
        },
        error: () => {
          this.isDetailsLoading.set(false);
          this.detailsError.set(true);
        },
      });
  }

  openDelete(tenant: Company): void {
    this.tenantToDelete.set(tenant);
    this.showDeleteModal.set(true);
  }

  openBulkDelete(): void {
    if (!this.hasSelection()) return;
    this.tenantToDelete.set(null);
    this.showDeleteModal.set(true);
  }

  openForceDelete(tenant: Company): void {
    this.tenantToForceDelete.set(tenant);
    this.adminPasswordControl.setValue('', { emitEvent: false });
    this.showForceDeleteModal.set(true);
  }

  openImpersonate(tenant: Company): void {
    this.tenantToImpersonate.set(tenant);
    this.impersonatePasswordControl.setValue('', { emitEvent: false });
    this.showImpersonateModal.set(true);
  }

  handleImpersonateConfirmed(): void {
    const tenant = this.tenantToImpersonate();
    if (!tenant || this.isImpersonating()) return;

    const password = this.impersonatePasswordControl.value;
    if (!password || password.trim().length === 0) return;

    this.isImpersonating.set(true);

    this.authService
      .impersonateTenant(String(tenant.id), password.trim())
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.isImpersonating.set(false);
          this.showImpersonateModal.set(false);
          this.tenantToImpersonate.set(null);
          this.impersonatePasswordControl.setValue('', { emitEvent: false });

          const user = response.data?.user;
          const token = response.data?.token;
          const permissions = response.data?.permissions ?? [];
          if (user && token) {
            const impersonatedUser = {
              ...user,
              permissions,
              is_impersonating: true,
              impersonated_tenant: response.data?.impersonated_tenant,
            } as unknown as AuthUser;
            this.authStore.startImpersonation(impersonatedUser, token);
          }

          this.toast.success(`Você entrou como ${tenant.name}.`);
          void this.router.navigate(['/dashboard']);
        },
        error: (err) => {
          this.isImpersonating.set(false);
          const msg = err?.error?.message || 'Erro ao impersonar. Verifique a senha e tente novamente.';
          this.toast.error(msg);
        },
      });
  }

  handleFormSaved(_tenant: Company): void {
    this.showFormModal.set(false);
    this.selectedTenant.set(null);
    this.toast.success('Inquilino salvo com sucesso.');
    this.loadTenants();
  }

  toId(value: string | number): string {
    return String(value);
  }

  handleFormCancelled(): void {
    this.showFormModal.set(false);
    this.selectedTenant.set(null);
  }

  handleDeleteConfirmed(): void {
    if (this.isDeleting()) return;

    const single = this.tenantToDelete();
    const ids = single ? [String(single.id)] : this.selectedTenantIds();
    if (ids.length === 0) return;

    this.isDeleting.set(true);
    forkJoin(ids.map((id) => this.service.delete(id)))
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isDeleting.set(false);
          this.showDeleteModal.set(false);
          this.tenantToDelete.set(null);
          this.clearSelection();
          this.toast.success(
            ids.length > 1
              ? 'Inquilinos excluídos com sucesso.'
              : 'Inquilino excluído com sucesso.',
          );
          this.loadTenants();
        },
        error: () => {
          this.isDeleting.set(false);
          this.showDeleteModal.set(false);
        },
      });
  }

  handleRestore(item: Company): void {
    this.service
      .restore(item.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => this.loadTenants(),
        error: () => {},
      });
  }

  handleForceDeleteConfirmed(): void {
    const target = this.tenantToForceDelete();
    if (!target || this.isForceDeleting()) return;

    const password = this.adminPasswordControl.value;
    if (!password || password.trim().length === 0) return;

    this.isForceDeleting.set(true);

    this.service
      .purge(target.id, password.trim())
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isForceDeleting.set(false);
          this.showForceDeleteModal.set(false);
          this.tenantToForceDelete.set(null);
          this.adminPasswordControl.setValue('', { emitEvent: false });
          this.toast.success('Inquilino excluído permanentemente com sucesso.');
          this.loadTenants();
        },
        error: (err) => {
          this.isForceDeleting.set(false);
          const msg = err?.error?.message || 'Erro ao excluir permanentemente. Verifique a senha e tente novamente.';
          this.toast.error(msg);
        },
      });
  }

  onSearch(term: string): void {
    this.currentSearchTerm.set(term);
    this.pageNumber.set(1);
    this.loadTenants();
  }

  loadPage(page: number): void {
    this.pageNumber.set(page);
    this.loadTenants();
  }

  onSort(event: { field: string; dir: SortDirection }): void {
    const isAllowedField =
      event.field === 'name' ||
      event.field === 'document' ||
      event.field === 'is_active' ||
      event.field === 'created_at';
    if (!isAllowedField) return;

    this.sortBy.set(event.field as TenantSortField);
    this.sortDir.set(event.dir);
    this.pageNumber.set(1);
    this.loadTenants();
  }

  retry(): void {
    this.loadTenants();
  }

  handleExportError(message: string): void {
    this.toast.error(message);
  }

  formatDocument(value: string | undefined): string {
    if (value === undefined || value === null || value.trim() === '') return '—';
    const digits = value.replace(/\D/g, '');
    if (digits.length === 11) return digits.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    if (digits.length === 14)
      return digits.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
    return value;
  }

  formatPhone(value: string | undefined): string {
    if (value === undefined || value === null || value.trim() === '') return '—';

    const digits = value.replace(/\D/g, '');
    const withCountryCode = this.formatPhoneWithCountryCode(digits);
    if (withCountryCode !== null) return withCountryCode;

    if (digits.length === 11) return digits.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    if (digits.length === 10) return digits.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
    return value;
  }

  private formatPhoneWithCountryCode(digits: string): string | null {
    const countryCodes = ['351', '55', '57', '56', '54', '52', '49', '44', '34', '1'];

    for (const code of countryCodes) {
      if (!digits.startsWith(code)) continue;

      const local = digits.slice(code.length);
      if (local.length === 11) {
        return `+${code} ${local.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3')}`;
      }
      if (local.length === 10) {
        return `+${code} ${local.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3')}`;
      }
      if (local.length === 9) {
        return `+${code} ${local.replace(/(\d{5})(\d{4})/, '$1-$2')}`;
      }
      if (local.length === 8) {
        return `+${code} ${local.replace(/(\d{4})(\d{4})/, '$1-$2')}`;
      }
    }

    return null;
  }

  private loadTenants(): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    const statusValue = this.filterStatusControl.value;
    const is_active =
      statusValue === 'active' ? true : statusValue === 'inactive' ? false : undefined;

    this.service
      .list({
        search: this.currentSearchTerm() || undefined,
        page: this.pageNumber(),
        per_page: 15,
        sort_by: this.sortBy(),
        sort_dir: this.sortDir(),
        is_active,
        trashed: statusValue === 'trashed',
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.tenants.set(response.data);
          this.meta.set(response.meta);
          this.syncSelectionControls(response.data);
          this.isLoading.set(false);
        },
        error: () => {
          this.isLoading.set(false);
          this.hasError.set(true);
        },
      });
  }

  getRowSelectionControl(id: string): FormControl<boolean> {
    const existing = this.rowSelectionControls.get(id);
    if (existing) return existing;

    const control = new FormControl<boolean>(false, { nonNullable: true });
    control.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((checked) => {
      const selected = new Set(this.selectedTenantIds());
      if (checked) selected.add(id);
      else selected.delete(id);
      this.selectedTenantIds.set([...selected]);

      const allSelected =
        this.tenants().length > 0 &&
        this.tenants().every((tenant) => selected.has(String(tenant.id)));
      this.selectAllControl.setValue(allSelected, { emitEvent: false });
    });

    this.rowSelectionControls.set(id, control);
    return control;
  }

  private syncSelectionControls(tenants: Company[]): void {
    const activeIds = new Set(tenants.map((tenant) => String(tenant.id)));
    for (const key of this.rowSelectionControls.keys()) {
      if (!activeIds.has(key)) this.rowSelectionControls.delete(key);
    }

    const selected = this.selectedTenantIds().filter((id) => activeIds.has(id));
    this.selectedTenantIds.set(selected);

    for (const tenant of tenants) {
      this.getRowSelectionControl(String(tenant.id)).setValue(selected.includes(String(tenant.id)));
    }

    this.selectAllControl.setValue(tenants.length > 0 && selected.length === tenants.length, {
      emitEvent: false,
    });
  }

  private clearSelection(): void {
    this.selectedTenantIds.set([]);
    this.selectAllControl.setValue(false, { emitEvent: false });
    for (const control of this.rowSelectionControls.values()) {
      control.setValue(false, { emitEvent: false });
    }
  }
}
