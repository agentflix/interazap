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
  AfLoadingButtonComponent,
  AfModalComponent,
  AfStatusBadgeComponent,
  AfTableActionsComponent,
} from '@shared/components';
import { type PlatformPlan } from '@platform/models/platform-plan.model';
import { PlatformPlanService } from '@platform/services/platform-plan.service';
import { ToastService } from '@core/services/toast.service';
import { formatCurrency } from '@shared/utils/currency';
import { PlanCrudFormComponent } from './components/plan-crud-form/plan-crud-form';

@Component({
  selector: 'app-platform-plan-list',
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
    AfDataTableComponent,
    AfStatusBadgeComponent,
    AfTableActionsComponent,
    AfAlertComponent,
    PlanCrudFormComponent,
/**
 * Plan list page component for the Platform module.
 * @selector app-platform-plan-list
 */
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './plan-list.html',
})
export class PlanListComponent implements OnInit {
  private readonly service = inject(PlatformPlanService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);

  readonly planFormRef = viewChild<PlanCrudFormComponent>('planForm');
  readonly isFormSaving = computed(() => this.planFormRef()?.isSaving() ?? false);

  readonly plans = signal<PlatformPlan[]>([]);
  readonly isLoading = signal(true);
  readonly hasError = signal(false);
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.plans().length === 0,
  );

  readonly selectAllControl = new FormControl<boolean>(false, { nonNullable: true });
  private readonly rowSelectionControls = new Map<string, FormControl<boolean>>();
  readonly selectedPlanIds = signal<string[]>([]);
  readonly selectedCount = computed(() => this.selectedPlanIds().length);
  readonly hasSelection = computed(() => this.selectedCount() > 0);

  readonly showFormModal = signal(false);
  readonly selectedPlan = signal<PlatformPlan | null>(null);
  readonly formModalTitle = computed(() => (this.selectedPlan() ? 'Editar plano' : 'Novo plano'));

  readonly showDeleteModal = signal(false);
  readonly planToDelete = signal<PlatformPlan | null>(null);
  readonly isDeleting = signal(false);
  readonly deleteModalTitle = computed(() =>
    this.planToDelete() ? 'Excluir plano' : 'Excluir planos',
  );
  readonly deleteConfirmLabel = computed(() =>
    this.planToDelete() ? 'Excluir' : 'Excluir selecionados',
  );
  readonly deleteMessage = computed(() => {
    const single = this.planToDelete();
    if (single) return `Tem certeza que deseja excluir o plano "${single.name}"?`;
    return `Tem certeza que deseja excluir os ${this.selectedCount()} planos selecionados?`;
  });

  private pageNumber = signal(1);
  private searchTerm = signal('');

  constructor() {
    this.selectAllControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((checked) => {
        const next = checked ? this.plans().map((p) => p.id) : [];
        this.selectedPlanIds.set(next);
        for (const plan of this.plans()) {
          this.getRowSelectionControl(plan.id).setValue(checked);
        }
      });
  }

  ngOnInit(): void {
    this.loadPlans();
  }

  openCreate(): void {
    this.selectedPlan.set(null);
    this.showFormModal.set(true);
  }

  openEdit(plan: PlatformPlan): void {
    this.selectedPlan.set(plan);
    this.showFormModal.set(true);
  }

  openDelete(plan: PlatformPlan): void {
    this.planToDelete.set(plan);
    this.showDeleteModal.set(true);
  }

  openBulkDelete(): void {
    if (!this.hasSelection()) return;
    this.planToDelete.set(null);
    this.showDeleteModal.set(true);
  }

  handleFormSaved(_plan: PlatformPlan): void {
    this.showFormModal.set(false);
    this.selectedPlan.set(null);
    this.toast.success('Plano salvo com sucesso.');
    this.loadPlans();
  }

  handleFormCancelled(): void {
    this.showFormModal.set(false);
    this.selectedPlan.set(null);
  }

  handleDeleteConfirmed(): void {
    if (this.isDeleting()) return;

    const single = this.planToDelete();
    const ids = single ? [single.id] : this.selectedPlanIds();
    if (ids.length === 0) return;

    this.isDeleting.set(true);
    forkJoin(ids.map((id) => this.service.delete(id)))
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isDeleting.set(false);
          this.showDeleteModal.set(false);
          this.planToDelete.set(null);
          this.clearSelection();
          this.toast.success(
            ids.length > 1 ? 'Planos excluídos com sucesso.' : 'Plano excluído com sucesso.',
          );
          this.loadPlans();
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
    this.loadPlans();
  }

  loadPage(page: number): void {
    this.pageNumber.set(page);
    this.loadPlans();
  }

  retry(): void {
    this.loadPlans();
  }

  formatCurrency(value: string | number): string {
    return formatCurrency(Number(value));
  }

  toGb(bytes?: number | null): number | null {
    if (!bytes) return null;
    return Math.round(bytes / 1024 / 1024 / 1024);
  }

  private loadPlans(): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    this.service
      .list({
        search: this.searchTerm() || undefined,
        page: this.pageNumber(),
        per_page: 15,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.plans.set(response.data);
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
      const selected = new Set(this.selectedPlanIds());
      if (checked) selected.add(id);
      else selected.delete(id);
      this.selectedPlanIds.set([...selected]);

      const allSelected = this.plans().length > 0 && this.plans().every((p) => selected.has(p.id));
      this.selectAllControl.setValue(allSelected, { emitEvent: false });
    });

    this.rowSelectionControls.set(id, control);
    return control;
  }

  private syncSelectionControls(plans: PlatformPlan[]): void {
    const activeIds = new Set(plans.map((p) => p.id));
    for (const key of this.rowSelectionControls.keys()) {
      if (!activeIds.has(key)) this.rowSelectionControls.delete(key);
    }

    const selected = this.selectedPlanIds().filter((id) => activeIds.has(id));
    this.selectedPlanIds.set(selected);

    for (const plan of plans) {
      this.getRowSelectionControl(plan.id).setValue(selected.includes(plan.id));
    }

    this.selectAllControl.setValue(plans.length > 0 && selected.length === plans.length, {
      emitEvent: false,
    });
  }

  private clearSelection(): void {
    this.selectedPlanIds.set([]);
    this.selectAllControl.setValue(false, { emitEvent: false });
    for (const control of this.rowSelectionControls.values()) {
      control.setValue(false, { emitEvent: false });
    }
  }
}
