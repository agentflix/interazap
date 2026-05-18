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
import { ReasonLossService } from '@core/services/crm-reason-loss.service';
import type { ReasonLoss } from '@core/models/reason-loss.model';
import { ReasonLossFormComponent } from './components/reason-loss-form/crm-reason-loss-form';

/**
 * Reason Losses CRM page — CRUD with client-side search filtering.
 * Business logic preserved verbatim from source.
 */
@Component({
  selector: 'app-reason-losses',
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
    ReasonLossFormComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './crm-reason-losses.html',
})
export class ReasonLosses implements OnInit {
  private readonly reasonLossService = inject(ReasonLossService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);

  readonly reasonFormRef = viewChild<ReasonLossFormComponent>('reasonForm');
  readonly isFormSaving = computed(() => this.reasonFormRef()?.isSaving() ?? false);

  // ─── List state ────────────────────────────────────────────────────────────
  readonly reasons = signal<ReasonLoss[]>([]);
  readonly isLoading = signal(true);
  readonly hasError = signal(false);
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });

  readonly searchTerm = signal('');
  private readonly pageNumber = signal(1);
  readonly sortBy = signal<string>('name');
  readonly sortDir = signal<SortDirection>('asc');

  /** Client-side search filtering (matching original behavior) */
  readonly filteredReasons = computed(() => {
    const search = this.searchTerm().toLowerCase();
    if (!search) return this.reasons();
    return this.reasons().filter(
      (r) => r.name.toLowerCase().includes(search) || r.description?.toLowerCase().includes(search),
    );
  });

  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.filteredReasons().length === 0,
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
  readonly selectedReason = signal<ReasonLoss | null>(null);
  readonly formModalTitle = computed(() =>
    this.selectedReason() ? 'Editar motivo de perda' : 'Novo motivo de perda',
  );

  readonly showDeleteModal = signal(false);
  readonly reasonToDelete = signal<ReasonLoss | null>(null);
  readonly isDeleting = signal(false);

  readonly selectAllControl = new FormControl<boolean>(false, { nonNullable: true });
  private readonly rowSelectionControls = new Map<string, FormControl<boolean>>();
  readonly selectedReasonIds = signal<string[]>([]);
  readonly selectedCount = computed(() => this.selectedReasonIds().length);
  readonly hasSelection = computed(() => this.selectedCount() > 0);

  readonly deleteModalTitle = computed(() =>
    this.reasonToDelete() ? 'Excluir motivo' : 'Excluir motivos',
  );
  readonly deleteConfirmLabel = computed(() =>
    this.reasonToDelete() ? 'Excluir' : 'Excluir selecionados',
  );
  readonly deleteMessage = computed(() => {
    const r = this.reasonToDelete();
    if (!r && this.selectedCount() > 0) {
      return `Tem certeza que deseja excluir ${this.selectedCount()} motivos selecionados? Esta ação não pode ser desfeita.`;
    }
    return r
      ? `Tem certeza que deseja excluir o motivo "${r.name}"? Esta ação não pode ser desfeita.`
      : 'Tem certeza que deseja excluir este motivo?';
  });

  constructor() {
    this.filterStatusControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        this.pageNumber.set(1);
        this.loadReasons();
      });

    this.selectAllControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((checked) => {
        const next = checked ? this.filteredReasons().map((r) => r.id) : [];
        this.selectedReasonIds.set(next);
        for (const r of this.filteredReasons()) {
          this.getRowSelectionControl(r.id).setValue(checked, { emitEvent: false });
        }
      });
  }

  ngOnInit(): void {
    this.loadReasons();
  }

  // ─── Actions ───────────────────────────────────────────────────────────────
  openCreate(): void {
    this.selectedReason.set(null);
    this.showFormModal.set(true);
  }

  openEdit(reason: ReasonLoss): void {
    this.selectedReason.set(reason);
    this.showFormModal.set(true);
  }

  openDelete(reason: ReasonLoss): void {
    this.reasonToDelete.set(reason);
    this.showDeleteModal.set(true);
  }

  openBulkDelete(): void {
    if (!this.hasSelection()) return;
    this.reasonToDelete.set(null);
    this.showDeleteModal.set(true);
  }

  handleFormSaved(_reason: ReasonLoss): void {
    this.showFormModal.set(false);
    this.selectedReason.set(null);
    this.toast.success('Motivo de perda salvo com sucesso.');
    this.loadReasons();
  }

  handleFormCancelled(): void {
    this.showFormModal.set(false);
    this.selectedReason.set(null);
  }

  handleDeleteConfirmed(): void {
    if (this.isDeleting()) return;
    const single = this.reasonToDelete();
    const ids = single ? [single.id] : this.selectedReasonIds();
    if (ids.length === 0) return;

    this.isDeleting.set(true);
    forkJoin(ids.map((id) => this.reasonLossService.delete(id))).subscribe({
      next: () => {
        this.isDeleting.set(false);
        this.showDeleteModal.set(false);
        this.reasonToDelete.set(null);
        this.clearSelection();
        this.toast.success(
          ids.length > 1
            ? 'Motivos de perda excluídos com sucesso.'
            : 'Motivo de perda excluído com sucesso.',
        );
        this.loadReasons();
      },
      error: () => {
        this.isDeleting.set(false);
        this.showDeleteModal.set(false);
      },
    });
  }

  onSearch(term: string): void {
    this.searchTerm.set(term);
  }

  loadPage(page: number): void {
    this.pageNumber.set(page);
    this.loadReasons();
  }

  onSort(event: { field: string; dir: SortDirection }): void {
    this.sortBy.set(event.field);
    this.sortDir.set(event.dir);
    this.pageNumber.set(1);
    this.loadReasons();
  }

  retry(): void {
    this.loadReasons();
  }

  // ─── Data loading ──────────────────────────────────────────────────────────
  private loadReasons(): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    const statusValue = this.filterStatusControl.value;
    const is_active =
      statusValue === 'active' ? true : statusValue === 'inactive' ? false : undefined;

    this.reasonLossService
      .list({
        page: this.pageNumber(),
        per_page: 15,
        sort_by: this.sortBy(),
        sort_dir: this.sortDir(),
        is_active,
      })
      .subscribe({
        next: (response) => {
          this.reasons.set(response.data);
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
      const selected = new Set(this.selectedReasonIds());
      if (checked) selected.add(id);
      else selected.delete(id);
      this.selectedReasonIds.set([...selected]);

      const reasons = this.filteredReasons();
      const allSelected = reasons.length > 0 && reasons.every((r) => selected.has(r.id));
      this.selectAllControl.setValue(allSelected, { emitEvent: false });
    });

    this.rowSelectionControls.set(id, control);
    return control;
  }

  private syncSelectionControls(reasons: ReasonLoss[]): void {
    const activeIds = new Set(reasons.map((r) => r.id));
    for (const key of this.rowSelectionControls.keys()) {
      if (!activeIds.has(key)) this.rowSelectionControls.delete(key);
    }
    const selected = this.selectedReasonIds().filter((id) => activeIds.has(id));
    this.selectedReasonIds.set(selected);
    for (const r of reasons) {
      this.getRowSelectionControl(r.id).setValue(selected.includes(r.id), { emitEvent: false });
    }
    this.selectAllControl.setValue(reasons.length > 0 && selected.length === reasons.length, {
      emitEvent: false,
    });
  }

  private clearSelection(): void {
    this.selectedReasonIds.set([]);
    this.selectAllControl.setValue(false, { emitEvent: false });
    for (const control of this.rowSelectionControls.values()) {
      control.setValue(false, { emitEvent: false });
    }
  }
}

export default ReasonLosses;
