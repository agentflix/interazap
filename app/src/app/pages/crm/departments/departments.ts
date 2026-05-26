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
  AfLoadingButtonComponent,
  AfModalComponent,
  AfSelectInputComponent,
  AfSortableHeaderComponent,
  AfStatusBadgeComponent,
  AfTableActionsComponent,
  type AfSelectOption,
  type SortDirection,
} from '@shared/components';
import { ToastService } from '@core/services/toast.service';
import { DepartmentService } from '@core/services/department.service';
import type { Department } from '@core/models/department.model';
import { DepartmentFormComponent } from './components/department-form/department-form';

/**
 * Página de configurações de departamentos do CRM com CRUD completo.
 */
@Component({
  selector: 'app-departments',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfCrudPageComponent,
    AfModalComponent,
    AfConfirmModalComponent,
    AfButtonComponent,
    AfCheckboxInputComponent,
    AfLoadingButtonComponent,
    AfSelectInputComponent,
    AfDataTableComponent,
    AfDrawerComponent,
    AfSortableHeaderComponent,
    AfStatusBadgeComponent,
    AfTableActionsComponent,
    AfAlertComponent,
    DepartmentFormComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './departments.html',
})
export class Departments implements OnInit {
  private readonly departmentService = inject(DepartmentService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly toast = inject(ToastService);

  /** Form component reference — only defined while modal is open */
  readonly departmentFormRef = viewChild<DepartmentFormComponent>('departmentForm');

  /** Proxy for form isSaving state — safe when modal is closed */
  readonly isFormSaving = computed(() => this.departmentFormRef()?.isSaving() ?? false);

  // ─── List state ────────────────────────────────────────────────────────────
  readonly departments = signal<Department[]>([]);
  readonly isLoading = signal(true);
  readonly hasError = signal(false);
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });

  private readonly searchTerm = signal('');
  private readonly pageNumber = signal(1);
  readonly sortBy = signal<string>('name');
  readonly sortDir = signal<SortDirection>('asc');

  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.departments().length === 0,
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
  readonly selectedDepartment = signal<Department | null>(null);

  readonly formModalTitle = computed(() =>
    this.selectedDepartment() ? 'Editar departamento' : 'Novo departamento',
  );

  readonly showDeleteModal = signal(false);
  readonly departmentToDelete = signal<Department | null>(null);

  readonly selectAllControl = new FormControl<boolean>(false, { nonNullable: true });
  private readonly rowSelectionControls = new Map<string, FormControl<boolean>>();
  readonly selectedDepartmentIds = signal<string[]>([]);
  readonly selectedCount = computed(() => this.selectedDepartmentIds().length);
  readonly hasSelection = computed(() => this.selectedCount() > 0);

  readonly deleteModalTitle = computed(() =>
    this.departmentToDelete() ? 'Excluir departamento' : 'Excluir departamentos',
  );

  readonly deleteConfirmLabel = computed(() =>
    this.departmentToDelete() ? 'Excluir' : 'Excluir selecionados',
  );

  readonly deleteMessage = computed(() => {
    const dept = this.departmentToDelete();
    if (!dept && this.selectedCount() > 0) {
      return `Tem certeza que deseja excluir ${this.selectedCount()} departamentos selecionados? Esta ação não pode ser desfeita.`;
    }
    return dept
      ? `Tem certeza que deseja excluir o departamento "${dept.name}"? Esta ação não pode ser desfeita.`
      : 'Tem certeza que deseja excluir este departamento?';
  });

  readonly isDeleting = signal(false);

  constructor() {
    // Reload list whenever status filter changes
    this.filterStatusControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        this.pageNumber.set(1);
        this.loadDepartments();
      });

    this.selectAllControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((checked) => {
        const nextSelected = checked ? this.departments().map((dept) => dept.id) : [];
        this.selectedDepartmentIds.set(nextSelected);

        for (const dept of this.departments()) {
          this.getRowSelectionControl(dept.id).setValue(checked, { emitEvent: false });
        }
      });
  }

  ngOnInit(): void {
    this.loadDepartments();
  }

  // ─── Actions ───────────────────────────────────────────────────────────────

  openCreate(): void {
    this.selectedDepartment.set(null);
    this.showFormModal.set(true);
  }

  openEdit(dept: Department): void {
    this.selectedDepartment.set(dept);
    this.showFormModal.set(true);
  }

  openDelete(dept: Department): void {
    this.departmentToDelete.set(dept);
    this.showDeleteModal.set(true);
  }

  openBulkDelete(): void {
    if (!this.hasSelection()) return;
    this.departmentToDelete.set(null);
    this.showDeleteModal.set(true);
  }

  handleFormSaved(dept: Department): void {
    this.showFormModal.set(false);
    this.selectedDepartment.set(null);
    this.toast.success('Departamento salvo com sucesso.');
    this.departments.update((list) => list.map((d) => (d.id === dept.id ? dept : d)));
    this.loadDepartments();
  }

  handleFormCancelled(): void {
    this.showFormModal.set(false);
    this.selectedDepartment.set(null);
  }

  handleDeleteConfirmed(): void {
    if (this.isDeleting()) return;

    const singleDepartment = this.departmentToDelete();
    const idsToDelete = singleDepartment ? [singleDepartment.id] : this.selectedDepartmentIds();

    if (idsToDelete.length === 0) return;

    this.isDeleting.set(true);

    forkJoin(idsToDelete.map((id) => this.departmentService.delete(id))).subscribe({
      next: () => {
        this.isDeleting.set(false);
        this.showDeleteModal.set(false);
        this.departmentToDelete.set(null);
        this.clearSelection();
        this.toast.success(
          idsToDelete.length > 1
            ? 'Departamentos excluídos com sucesso.'
            : 'Departamento excluído com sucesso.',
        );
        this.loadDepartments();
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
    this.loadDepartments();
  }

  loadPage(page: number): void {
    this.pageNumber.set(page);
    this.loadDepartments();
  }

  onSort(event: { field: string; dir: SortDirection }): void {
    this.sortBy.set(event.field);
    this.sortDir.set(event.dir);
    this.pageNumber.set(1);
    this.loadDepartments();
  }

  retry(): void {
    this.loadDepartments();
  }

  // ─── Data loading ──────────────────────────────────────────────────────────

  private loadDepartments(): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    const statusValue = this.filterStatusControl.value;
    const is_active =
      statusValue === 'active' ? true : statusValue === 'inactive' ? false : undefined;

    this.departmentService
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
          this.departments.set(response.data);
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
      const selected = new Set(this.selectedDepartmentIds());
      if (checked) {
        selected.add(id);
      } else {
        selected.delete(id);
      }

      this.selectedDepartmentIds.set([...selected]);

      const allSelected =
        this.departments().length > 0 && this.departments().every((dept) => selected.has(dept.id));
      this.selectAllControl.setValue(allSelected, { emitEvent: false });
    });

    this.rowSelectionControls.set(id, control);
    return control;
  }

  private syncSelectionControls(departments: Department[]): void {
    const activeIds = new Set(departments.map((dept) => dept.id));

    for (const key of this.rowSelectionControls.keys()) {
      if (!activeIds.has(key)) {
        this.rowSelectionControls.delete(key);
      }
    }

    const selected = this.selectedDepartmentIds().filter((id) => activeIds.has(id));
    this.selectedDepartmentIds.set(selected);

    for (const dept of departments) {
      this.getRowSelectionControl(dept.id).setValue(selected.includes(dept.id), {
        emitEvent: false,
      });
    }

    this.selectAllControl.setValue(
      departments.length > 0 && selected.length === departments.length,
      {
        emitEvent: false,
      },
    );
  }

  private clearSelection(): void {
    this.selectedDepartmentIds.set([]);
    this.selectAllControl.setValue(false, { emitEvent: false });
    for (const control of this.rowSelectionControls.values()) {
      control.setValue(false, { emitEvent: false });
    }
  }
}

export default Departments;
