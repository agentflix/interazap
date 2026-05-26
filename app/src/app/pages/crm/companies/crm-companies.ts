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
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute, Router } from '@angular/router';
import { forkJoin } from 'rxjs';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfCheckboxInputComponent,
  AfCrudPageComponent,
  AfConfirmModalComponent,
  AfDataTableComponent,
  AfDrawerComponent,
  AfIconButtonComponent,
  AfLoadingButtonComponent,
  AfModalComponent,
  AfScrollAreaComponent,
  AfSelectInputComponent,
  AfSortableHeaderComponent,
  AfStatusBadgeComponent,
  AfTableActionsComponent,
  type AfSelectOption,
  type SortDirection,
} from '@shared/components';
import { ToastService } from '@core/services/toast.service';
import { type CRMCompany, CRMCompanyService } from '@core/services/crm-company.service';
import { CompanyFormComponent } from './components/company-form/crm-company-form';

/**
 * Página de empresas do CRM com CRUD completo e painel de detalhes lateral.
 * Suporta filtro por status, busca, ordenação, seleção em lote e exclusão múltipla.
 */
@Component({
  selector: 'app-companies',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfCrudPageComponent,
    AfModalComponent,
    AfConfirmModalComponent,
    AfButtonComponent,
    AfCheckboxInputComponent,
    AfIconButtonComponent,
    AfLoadingButtonComponent,
    AfScrollAreaComponent,
    AfSelectInputComponent,
    AfDataTableComponent,
    AfDrawerComponent,
    AfSortableHeaderComponent,
    AfStatusBadgeComponent,
    AfTableActionsComponent,
    AfAlertComponent,
    CompanyFormComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './crm-companies.html',
})
export class Companies implements OnInit {
  private readonly crmCompanyService = inject(CRMCompanyService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private routeHandled = false;

  readonly companyFormRef = viewChild<CompanyFormComponent>('companyForm');
  readonly isFormSaving = computed(() => this.companyFormRef()?.isSaving() ?? false);

  // ─── List state ────────────────────────────────────────────────────────────
  readonly companies = signal<CRMCompany[]>([]);
  readonly isLoading = signal(true);
  readonly hasError = signal(false);
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });

  readonly searchTerm = signal('');
  private readonly pageNumber = signal(1);
  readonly sortBy = signal<string>('name');
  readonly sortDir = signal<SortDirection>('asc');

  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.companies().length === 0,
  );

  // ─── Filter drawer ─────────────────────────────────────────────────────────
  readonly isFilterOpen = signal(false);

  readonly activeFiltersCount = computed(() =>
    this.filterStatusControl.value !== 'all' ? 1 : 0,
  );

  openFilter(): void { this.isFilterOpen.set(true); }
  closeFilter(): void { this.isFilterOpen.set(false); }

  clearFilter(): void {
    this.filterStatusControl.setValue('all');
  }

  applyFilter(): void {
    this.closeFilter();
  }

  // ─── Filter ────────────────────────────────────────────────────────────────
  readonly filterStatusControl = new FormControl<string>('all', { nonNullable: true });
  readonly filterStatusOptions: AfSelectOption[] = [
    { label: 'Todos', value: 'all' },
    { label: 'Ativo', value: 'active' },
    { label: 'Inativo', value: 'inactive' },
  ];

  // ─── Modal state ───────────────────────────────────────────────────────────
  readonly showFormModal = signal(false);
  readonly selectedCompany = signal<CRMCompany | null>(null);
  readonly formModalTitle = computed(() =>
    this.selectedCompany() ? 'Editar empresa' : 'Nova empresa',
  );

  readonly showDeleteModal = signal(false);
  readonly companyToDelete = signal<CRMCompany | null>(null);
  readonly isDeleting = signal(false);

  readonly selectAllControl = new FormControl<boolean>(false, { nonNullable: true });
  private readonly rowSelectionControls = new Map<string, FormControl<boolean>>();
  readonly selectedCompanyIds = signal<string[]>([]);
  readonly selectedCount = computed(() => this.selectedCompanyIds().length);
  readonly hasSelection = computed(() => this.selectedCount() > 0);

  readonly deleteModalTitle = computed(() =>
    this.companyToDelete() ? 'Excluir empresa' : 'Excluir empresas',
  );
  readonly deleteConfirmLabel = computed(() =>
    this.companyToDelete() ? 'Excluir' : 'Excluir selecionadas',
  );
  readonly deleteMessage = computed(() => {
    const c = this.companyToDelete();
    if (!c && this.selectedCount() > 0) {
      return `Tem certeza que deseja excluir ${this.selectedCount()} empresas selecionadas? Esta ação não pode ser desfeita.`;
    }
    return c
      ? `Tem certeza que deseja excluir a empresa "${c.name}"? Esta ação não pode ser desfeita.`
      : 'Tem certeza que deseja excluir esta empresa?';
  });

  // ─── Details panel ─────────────────────────────────────────────────────────
  readonly detailCompany = signal<CRMCompany | null>(null);
  readonly isDetailsOpen = signal(false);

  constructor() {
    this.filterStatusControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        this.pageNumber.set(1);
        this.loadCompanies();
      });

    this.selectAllControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((checked) => {
        const next = checked ? this.companies().map((c) => c.id) : [];
        this.selectedCompanyIds.set(next);
        for (const c of this.companies()) {
          this.getRowSelectionControl(c.id).setValue(checked);
        }
      });
  }

  ngOnInit(): void {
    this.loadCompanies();
    this.handleRouteCompany();
  }

  // ─── Actions ───────────────────────────────────────────────────────────────
  openCreate(): void {
    this.selectedCompany.set(null);
    this.showFormModal.set(true);
  }

  openEdit(company: CRMCompany): void {
    this.selectedCompany.set(company);
    this.showFormModal.set(true);
  }

  openDelete(company: CRMCompany): void {
    this.companyToDelete.set(company);
    this.showDeleteModal.set(true);
  }

  openBulkDelete(): void {
    if (!this.hasSelection()) return;
    this.companyToDelete.set(null);
    this.showDeleteModal.set(true);
  }

  openDetails(company: CRMCompany): void {
    this.detailCompany.set(company);
    this.isDetailsOpen.set(true);
  }

  closeDetails(): void {
    this.isDetailsOpen.set(false);
  }

  handleFormSaved(_company: CRMCompany): void {
    this.showFormModal.set(false);
    this.selectedCompany.set(null);
    this.toast.success('Empresa salva com sucesso.');
    this.loadCompanies();
  }

  handleFormCancelled(): void {
    this.showFormModal.set(false);
    this.selectedCompany.set(null);
  }

  handleDeleteConfirmed(): void {
    if (this.isDeleting()) return;
    const single = this.companyToDelete();
    const ids = single ? [single.id] : this.selectedCompanyIds();
    if (ids.length === 0) return;

    this.isDeleting.set(true);
    forkJoin(ids.map((id) => this.crmCompanyService.delete(id))).subscribe({
      next: () => {
        this.isDeleting.set(false);
        this.showDeleteModal.set(false);
        this.companyToDelete.set(null);
        this.clearSelection();
        this.toast.success(
          ids.length > 1 ? 'Empresas excluídas com sucesso.' : 'Empresa excluída com sucesso.',
        );
        this.loadCompanies();
      },
      error: () => {
        this.isDeleting.set(false);
        this.showDeleteModal.set(false);
      },
    });
  }

  onSearch(term: string): void {
    this.searchTerm.set(term);
    this.pageNumber.set(1);
    this.loadCompanies();
  }

  loadPage(page: number): void {
    this.pageNumber.set(page);
    this.loadCompanies();
  }

  onSort(event: { field: string; dir: SortDirection }): void {
    this.sortBy.set(event.field);
    this.sortDir.set(event.dir);
    this.pageNumber.set(1);
    this.loadCompanies();
  }

  retry(): void {
    this.loadCompanies();
  }

  formatPhone(value: string | undefined): string {
    if (!value) return '—';
    const digits = value.replace(/\D/g, '');
    if (digits.startsWith('55')) {
      const local = digits.slice(2);
      if (local.length === 11) return `+55 ${local.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3')}`;
      if (local.length === 10) return `+55 ${local.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3')}`;
    }
    if (digits.length === 11) return digits.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    if (digits.length === 10) return digits.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
    return value;
  }

  formatDocument(value: string | undefined): string {
    if (!value) return '—';
    const d = value.replace(/\D/g, '');
    if (d.length === 11) return d.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    if (d.length === 14) return d.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
    return value;
  }

  // ─── Data loading ──────────────────────────────────────────────────────────
  private loadCompanies(): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    const statusValue = this.filterStatusControl.value;
    const is_active =
      statusValue === 'active' ? true : statusValue === 'inactive' ? false : undefined;

    this.crmCompanyService
      .list({
        search: this.searchTerm() || undefined,
        page: this.pageNumber(),
        per_page: 15,
        sort_by: this.sortBy(),
        sort_dir: this.sortDir(),
        is_active,
      })
      .subscribe({
        next: (response) => {
          this.companies.set(response.data);
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

  private handleRouteCompany(): void {
    this.route.queryParamMap.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((params) => {
      const companyId = params.get('company_id');
      if (!companyId || this.routeHandled) return;
      this.routeHandled = true;
      this.crmCompanyService.get(companyId).subscribe({
        next: (response) => {
          this.openEdit(response.data);
          void this.router.navigate([], {
            queryParams: { company_id: null },
            queryParamsHandling: 'merge',
            replaceUrl: true,
          });
        },
      });
    });
  }

  getRowSelectionControl(id: string): FormControl<boolean> {
    const existing = this.rowSelectionControls.get(id);
    if (existing) return existing;

    const control = new FormControl<boolean>(false, { nonNullable: true });
    control.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((checked) => {
      const selected = new Set(this.selectedCompanyIds());
      if (checked) selected.add(id);
      else selected.delete(id);
      this.selectedCompanyIds.set([...selected]);

      const allSelected =
        this.companies().length > 0 && this.companies().every((c) => selected.has(c.id));
      this.selectAllControl.setValue(allSelected, { emitEvent: false });
    });

    this.rowSelectionControls.set(id, control);
    return control;
  }

  private syncSelectionControls(companies: CRMCompany[]): void {
    const activeIds = new Set(companies.map((c) => c.id));
    for (const key of this.rowSelectionControls.keys()) {
      if (!activeIds.has(key)) this.rowSelectionControls.delete(key);
    }
    const selected = this.selectedCompanyIds().filter((id) => activeIds.has(id));
    this.selectedCompanyIds.set(selected);
    for (const c of companies) {
      this.getRowSelectionControl(c.id).setValue(selected.includes(c.id));
    }
    this.selectAllControl.setValue(companies.length > 0 && selected.length === companies.length, {
      emitEvent: false,
    });
  }

  private clearSelection(): void {
    this.selectedCompanyIds.set([]);
    this.selectAllControl.setValue(false, { emitEvent: false });
    for (const control of this.rowSelectionControls.values()) {
      control.setValue(false, { emitEvent: false });
    }
  }
}

export default Companies;
