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
  AfConfirmModalComponent,
  AfCrudPageComponent,
  AfDataTableComponent,
  AfDrawerComponent,
  AfLoadingButtonComponent,
  AfModalComponent,
  AfSelectInputComponent,
  AfStatusBadgeComponent,
  AfTableActionsComponent,
  type AfSelectOption,
  AfSortableHeaderComponent,
  type SortDirection,
} from '@shared/components';
import { ToastService } from '@core/services/toast.service';
import { FunnelService } from '@core/services/crm-funnel.service';
import type { Funnel } from '@core/models/funnel.model';
import { FunnelFormComponent } from './components/funnel-form/crm-funnel-form';

/**
 * Página de configurações de funis de vendas do CRM com CRUD completo.
 */
@Component({
  selector: 'app-funnels',
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
    FunnelFormComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './crm-funnels.html',
})
export class Funnels implements OnInit {
  private readonly funnelService = inject(FunnelService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);

  /** Form component reference — only defined while modal is open */
  readonly funnelFormRef = viewChild<FunnelFormComponent>('funnelForm');

  /** Proxy for form isSaving state — safe when modal is closed */
  readonly isFormSaving = computed(() => this.funnelFormRef()?.isSaving() ?? false);

  // ─── List state ────────────────────────────────────────────────────────────
  readonly funnels = signal<Funnel[]>([]);
  readonly isLoading = signal(true);
  readonly hasError = signal(false);
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });

  private readonly searchTerm = signal('');
  private readonly pageNumber = signal(1);
  readonly sortBy = signal<string>('name');
  readonly sortDir = signal<SortDirection>('asc');

  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.funnels().length === 0,
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
  readonly selectedFunnel = signal<Funnel | null>(null);

  readonly formModalTitle = computed(() => (this.selectedFunnel() ? 'Editar funil' : 'Novo funil'));

  readonly showDeleteModal = signal(false);
  readonly funnelToDelete = signal<Funnel | null>(null);

  readonly selectAllControl = new FormControl<boolean>(false, { nonNullable: true });
  private readonly rowSelectionControls = new Map<string, FormControl<boolean>>();
  readonly selectedFunnelIds = signal<string[]>([]);
  readonly selectedCount = computed(() => this.selectedFunnelIds().length);
  readonly hasSelection = computed(() => this.selectedCount() > 0);

  readonly deleteModalTitle = computed(() =>
    this.funnelToDelete() ? 'Excluir funil' : 'Excluir funis',
  );

  readonly deleteConfirmLabel = computed(() =>
    this.funnelToDelete() ? 'Excluir' : 'Excluir selecionados',
  );

  readonly deleteMessage = computed(() => {
    const funnel = this.funnelToDelete();
    if (!funnel && this.selectedCount() > 0) {
      return `Tem certeza que deseja excluir ${this.selectedCount()} funis selecionados? Esta ação não pode ser desfeita.`;
    }
    return funnel
      ? `Tem certeza que deseja excluir o funil "${funnel.name}"? Esta ação não pode ser desfeita.`
      : 'Tem certeza que deseja excluir este funil?';
  });

  readonly isDeleting = signal(false);

  constructor() {
    this.filterStatusControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        this.pageNumber.set(1);
        this.loadFunnels();
      });

    this.selectAllControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((checked) => {
        const nextSelected = checked ? this.funnels().map((f) => String(f.id)) : [];
        this.selectedFunnelIds.set(nextSelected);

        for (const funnel of this.funnels()) {
          this.getRowSelectionControl(String(funnel.id)).setValue(checked);
        }
      });
  }

  ngOnInit(): void {
    this.loadFunnels();
  }

  // ─── Helpers ───────────────────────────────────────────────────────────────

  getStepsCount(funnel: Funnel): number {
    return funnel.steps_count ?? funnel.steps?.length ?? 0;
  }

  // ─── Actions ───────────────────────────────────────────────────────────────

  openCreate(): void {
    this.selectedFunnel.set(null);
    this.showFormModal.set(true);
  }

  openEdit(funnel: Funnel): void {
    this.selectedFunnel.set(funnel);
    this.showFormModal.set(true);
  }

  openDelete(funnel: Funnel): void {
    this.funnelToDelete.set(funnel);
    this.showDeleteModal.set(true);
  }

  openBulkDelete(): void {
    if (!this.hasSelection()) return;
    this.funnelToDelete.set(null);
    this.showDeleteModal.set(true);
  }

  handleFormSaved(funnel: Funnel): void {
    this.showFormModal.set(false);
    this.selectedFunnel.set(null);
    this.toast.success('Funil salvo com sucesso.');
    this.funnels.update((list) => {
      const exists = list.some((f) => f.id === funnel.id);
      return exists ? list.map((f) => (f.id === funnel.id ? funnel : f)) : [funnel, ...list];
    });
    this.loadFunnels();
  }

  handleFormCancelled(): void {
    this.showFormModal.set(false);
    this.selectedFunnel.set(null);
  }

  handleDeleteConfirmed(): void {
    if (this.isDeleting()) return;

    const singleFunnel = this.funnelToDelete();
    const idsToDelete = singleFunnel ? [singleFunnel.id] : this.selectedFunnelIds();

    if (idsToDelete.length === 0) return;

    this.isDeleting.set(true);

    forkJoin(idsToDelete.map((id) => this.funnelService.delete(id))).subscribe({
      next: () => {
        this.isDeleting.set(false);
        this.showDeleteModal.set(false);
        this.funnelToDelete.set(null);
        this.clearSelection();
        this.toast.success(
          idsToDelete.length > 1 ? 'Funis excluídos com sucesso.' : 'Funil excluído com sucesso.',
        );
        this.loadFunnels();
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
    this.loadFunnels();
  }

  loadPage(page: number): void {
    this.pageNumber.set(page);
    this.loadFunnels();
  }

  onSort(event: { field: string; dir: SortDirection }): void {
    this.sortBy.set(event.field);
    this.sortDir.set(event.dir);
    this.pageNumber.set(1);
    this.loadFunnels();
  }

  retry(): void {
    this.loadFunnels();
  }

  // ─── Data loading ──────────────────────────────────────────────────────────

  private loadFunnels(): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    const statusValue = this.filterStatusControl.value;
    const is_active =
      statusValue === 'active' ? true : statusValue === 'inactive' ? false : undefined;

    this.funnelService
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
          this.funnels.set(response.data);
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

  getRowSelectionControl(id: string | number): FormControl<boolean> {
    const key = String(id);
    const existing = this.rowSelectionControls.get(key);
    if (existing) return existing;

    const control = new FormControl<boolean>(false, { nonNullable: true });
    control.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((checked) => {
      const selected = new Set(this.selectedFunnelIds());
      if (checked) {
        selected.add(key);
      } else {
        selected.delete(key);
      }

      this.selectedFunnelIds.set([...selected]);

      const allSelected =
        this.funnels().length > 0 && this.funnels().every((f) => selected.has(String(f.id)));
      this.selectAllControl.setValue(allSelected, { emitEvent: false });
    });

    this.rowSelectionControls.set(key, control);
    return control;
  }

  private syncSelectionControls(funnels: Funnel[]): void {
    const activeIds = new Set(funnels.map((f) => String(f.id)));

    for (const key of this.rowSelectionControls.keys()) {
      if (!activeIds.has(key)) {
        this.rowSelectionControls.delete(key);
      }
    }

    const selected = this.selectedFunnelIds().filter((id) => activeIds.has(id));
    this.selectedFunnelIds.set(selected);

    for (const funnel of funnels) {
      const funnelId = String(funnel.id);
      this.getRowSelectionControl(funnelId).setValue(selected.includes(funnelId));
    }

    this.selectAllControl.setValue(funnels.length > 0 && selected.length === funnels.length, {
      emitEvent: false,
    });
  }

  private clearSelection(): void {
    this.selectedFunnelIds.set([]);
    this.selectAllControl.setValue(false, { emitEvent: false });
    for (const control of this.rowSelectionControls.values()) {
      control.setValue(false, { emitEvent: false });
    }
  }
}
