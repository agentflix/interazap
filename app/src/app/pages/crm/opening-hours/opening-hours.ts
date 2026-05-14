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
  AfLoadingButtonComponent,
  AfModalComponent,
  AfStatusBadgeComponent,
  AfTableActionsComponent,
} from '@shared/components';
import { ToastService } from '@core/services/toast.service';
import { type OpeningHour, OpeningHourService } from '@core/services/opening-hour.service';
import { OpeningHoursFormComponent } from './components/opening-hours-form/opening-hours-form';

/**
 * Opening Hours settings page — CRUD for company schedule entries.
 * Business logic preserved verbatim from source. Visual layer migrated to UI Kit.
 *
 * Notes:
 * - The API returns all opening hours in one call (not paginated).
 * - Local search is applied on day-of-week label (same as original).
 * - Supports bulk-delete via checkbox selection (consistent with other CRUD pages).
 */
@Component({
  selector: 'app-opening-hours',
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
    OpeningHoursFormComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './opening-hours.html',
})
export class OpeningHours implements OnInit {
  private readonly openingHourService = inject(OpeningHourService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);

  /** Form component reference — only defined while modal is open */
  readonly openingHoursFormRef = viewChild<OpeningHoursFormComponent>('openingHoursForm');

  /** Proxy for form isSaving state — safe when modal is closed */
  readonly isFormSaving = computed(() => this.openingHoursFormRef()?.isSaving() ?? false);

  // ─── Day labels (original constants preserved) ─────────────────────────────
  readonly daysOfWeek = [
    { value: 0, label: 'Domingo' },
    { value: 1, label: 'Segunda-feira' },
    { value: 2, label: 'Terça-feira' },
    { value: 3, label: 'Quarta-feira' },
    { value: 4, label: 'Quinta-feira' },
    { value: 5, label: 'Sexta-feira' },
    { value: 6, label: 'Sábado' },
  ];

  // ─── List state ────────────────────────────────────────────────────────────
  readonly hours = signal<OpeningHour[]>([]);
  readonly isLoading = signal(true);
  readonly hasError = signal(false);

  /** Local search term (original: search done client-side on day label) */
  private readonly searchTerm = signal('');

  /** Filtered hours based on local search (sorted by day_of_week) */
  readonly filteredHours = computed(() => {
    const term = this.searchTerm().toLowerCase();
    const sorted = [...this.hours()].sort((a, b) => a.day_of_week - b.day_of_week);
    if (!term) return sorted;
    return sorted.filter((hour) => this.getDayLabel(hour.day_of_week).toLowerCase().includes(term));
  });

  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.filteredHours().length === 0,
  );

  // ─── Selection state (bulk delete) ─────────────────────────────────────────
  readonly selectAllControl = new FormControl<boolean>(false, { nonNullable: true });
  private readonly rowSelectionControls = new Map<string, FormControl<boolean>>();
  readonly selectedHourIds = signal<string[]>([]);
  readonly selectedCount = computed(() => this.selectedHourIds().length);
  readonly hasSelection = computed(() => this.selectedCount() > 0);

  // ─── Modal state ───────────────────────────────────────────────────────────
  readonly showFormModal = signal(false);
  readonly selectedHour = signal<OpeningHour | null>(null);

  readonly formModalTitle = computed(() =>
    this.selectedHour() ? 'Editar horário' : 'Novo horário',
  );

  readonly showDeleteModal = signal(false);
  readonly hourToDelete = signal<OpeningHour | null>(null);

  readonly deleteModalTitle = computed(() =>
    this.hourToDelete() ? 'Excluir horário' : 'Excluir horários',
  );

  readonly deleteConfirmLabel = computed(() =>
    this.hourToDelete() ? 'Excluir' : 'Excluir selecionados',
  );

  readonly deleteMessage = computed(() => {
    const single = this.hourToDelete();
    if (!single && this.selectedCount() > 0) {
      return `Tem certeza que deseja excluir ${this.selectedCount()} horários selecionados? Esta ação não pode ser desfeita.`;
    }
    return single
      ? `Tem certeza que deseja excluir o horário de ${this.getDayLabel(single.day_of_week)}? Esta ação não pode ser desfeita.`
      : 'Tem certeza que deseja excluir este horário?';
  });

  readonly isDeleting = signal(false);

  constructor() {
    this.selectAllControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((checked) => {
        const nextSelected = checked ? this.filteredHours().map((h) => h.id) : [];
        this.selectedHourIds.set(nextSelected);

        for (const hour of this.filteredHours()) {
          this.getRowSelectionControl(hour.id).setValue(checked);
        }
      });
  }

  ngOnInit(): void {
    this.loadHours();
  }

  // ─── Helpers (original methods preserved) ──────────────────────────────────

  /** Returns day name for a given weekday number (0 = Sunday … 6 = Saturday) */
  getDayLabel(value: number): string {
    return this.daysOfWeek.find((d) => d.value === value)?.label ?? 'Dia Desconhecido';
  }

  /** Trims seconds from HH:MM:SS → HH:MM */
  formatTime(time: string): string {
    return time ? time.substring(0, 5) : '';
  }

  // ─── Actions ───────────────────────────────────────────────────────────────

  openCreate(): void {
    this.selectedHour.set(null);
    this.showFormModal.set(true);
  }

  openEdit(hour: OpeningHour): void {
    this.selectedHour.set(hour);
    this.showFormModal.set(true);
  }

  openDelete(hour: OpeningHour): void {
    this.hourToDelete.set(hour);
    this.showDeleteModal.set(true);
  }

  openBulkDelete(): void {
    if (!this.hasSelection()) return;
    this.hourToDelete.set(null);
    this.showDeleteModal.set(true);
  }

  handleFormSaved(hour: OpeningHour): void {
    this.showFormModal.set(false);
    this.selectedHour.set(null);
    this.toast.success('Horário salvo com sucesso.');
    this.hours.update((list) => list.map((h) => (h.id === hour.id ? hour : h)));
    this.loadHours();
  }

  handleFormCancelled(): void {
    this.showFormModal.set(false);
    this.selectedHour.set(null);
  }

  handleDeleteConfirmed(): void {
    if (this.isDeleting()) return;

    const singleHour = this.hourToDelete();
    const idsToDelete = singleHour ? [singleHour.id] : this.selectedHourIds();

    if (idsToDelete.length === 0) return;

    this.isDeleting.set(true);

    forkJoin(idsToDelete.map((id) => this.openingHourService.delete(id)))
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isDeleting.set(false);
          this.showDeleteModal.set(false);
          this.hourToDelete.set(null);
          this.clearSelection();
          this.toast.success(
            idsToDelete.length > 1
              ? 'Horários excluídos com sucesso.'
              : 'Horário excluído com sucesso.',
          );
          this.loadHours();
        },
        error: () => {
          this.isDeleting.set(false);
          this.showDeleteModal.set(false);
        },
      });
  }

  /** Local search — filters on day label (original behaviour) */
  onSearch(term: string): void {
    this.searchTerm.set(term);
  }

  retry(): void {
    this.loadHours();
  }

  // ─── Selection helpers ─────────────────────────────────────────────────────

  getRowSelectionControl(id: string): FormControl<boolean> {
    const existing = this.rowSelectionControls.get(id);
    if (existing) return existing;

    const control = new FormControl<boolean>(false, { nonNullable: true });
    control.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((checked) => {
      const selected = new Set(this.selectedHourIds());
      if (checked) {
        selected.add(id);
      } else {
        selected.delete(id);
      }

      this.selectedHourIds.set([...selected]);

      const allSelected =
        this.filteredHours().length > 0 &&
        this.filteredHours().every((h) => selected.has(h.id));
      this.selectAllControl.setValue(allSelected, { emitEvent: false });
    });

    this.rowSelectionControls.set(id, control);
    return control;
  }

  private syncSelectionControls(hours: OpeningHour[]): void {
    const activeIds = new Set(hours.map((h) => h.id));

    for (const key of this.rowSelectionControls.keys()) {
      if (!activeIds.has(key)) {
        this.rowSelectionControls.delete(key);
      }
    }

    const selected = this.selectedHourIds().filter((id) => activeIds.has(id));
    this.selectedHourIds.set(selected);

    for (const hour of hours) {
      this.getRowSelectionControl(hour.id).setValue(selected.includes(hour.id));
    }

    this.selectAllControl.setValue(hours.length > 0 && selected.length === hours.length, {
      emitEvent: false,
    });
  }

  private clearSelection(): void {
    this.selectedHourIds.set([]);
    this.selectAllControl.setValue(false, { emitEvent: false });
    for (const control of this.rowSelectionControls.values()) {
      control.setValue(false, { emitEvent: false });
    }
  }

  // ─── Data loading ──────────────────────────────────────────────────────────

  private loadHours(): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    this.openingHourService
      .list()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const data = response.data.opening_hours ?? [];
          this.hours.set(data);
          this.syncSelectionControls(data);
          this.isLoading.set(false);
        },
        error: () => {
          this.isLoading.set(false);
          this.hasError.set(true);
        },
      });
  }
}

export default OpeningHours;
