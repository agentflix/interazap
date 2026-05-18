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
import { TagService } from '@core/services/tag.service';
import type { Tag } from '@core/models/tag.model';
import { TagFormComponent } from './components/tag-form/tag-form';

/**
 * Tags settings page — CRUD for CRM tags.
 * Business logic preserved verbatim from source. Visual layer migrated to UI Kit.
 */
@Component({
  selector: 'app-tags',
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
    TagFormComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './tags.html',
})
export class Tags implements OnInit {
  private readonly tagService = inject(TagService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly toast = inject(ToastService);

  /** Form component reference — only defined while modal is open */
  readonly tagFormRef = viewChild<TagFormComponent>('tagForm');

  /** Proxy for form isSaving state — safe when modal is closed */
  readonly isFormSaving = computed(() => this.tagFormRef()?.isSaving() ?? false);

  // ─── List state ────────────────────────────────────────────────────────────
  readonly tags = signal<Tag[]>([]);
  readonly isLoading = signal(true);
  readonly hasError = signal(false);
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });

  private readonly searchTerm = signal('');
  private readonly pageNumber = signal(1);
  readonly sortBy = signal<string>('name');
  readonly sortDir = signal<SortDirection>('asc');

  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.tags().length === 0,
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
  readonly selectedTag = signal<Tag | null>(null);

  readonly formModalTitle = computed(() =>
    this.selectedTag() ? 'Editar etiqueta' : 'Nova etiqueta',
  );

  readonly showDeleteModal = signal(false);
  readonly tagToDelete = signal<Tag | null>(null);

  readonly selectAllControl = new FormControl<boolean>(false, { nonNullable: true });
  private readonly rowSelectionControls = new Map<string, FormControl<boolean>>();
  readonly selectedTagIds = signal<string[]>([]);
  readonly selectedCount = computed(() => this.selectedTagIds().length);
  readonly hasSelection = computed(() => this.selectedCount() > 0);

  readonly deleteModalTitle = computed(() =>
    this.tagToDelete() ? 'Excluir etiqueta' : 'Excluir etiquetas',
  );

  readonly deleteConfirmLabel = computed(() =>
    this.tagToDelete() ? 'Excluir' : 'Excluir selecionadas',
  );

  readonly deleteMessage = computed(() => {
    const tag = this.tagToDelete();
    if (!tag && this.selectedCount() > 0) {
      return `Tem certeza que deseja excluir ${this.selectedCount()} etiquetas selecionadas? Esta ação não pode ser desfeita.`;
    }
    return tag
      ? `Tem certeza que deseja excluir a etiqueta "${tag.name}"? Esta ação não pode ser desfeita.`
      : 'Tem certeza que deseja excluir esta etiqueta?';
  });

  readonly isDeleting = signal(false);

  constructor() {
    // Reload list whenever status filter changes
    this.filterStatusControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        this.pageNumber.set(1);
        this.loadTags();
      });

    this.selectAllControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((checked) => {
        const nextSelected = checked ? this.tags().map((tag) => tag.id) : [];
        this.selectedTagIds.set(nextSelected);

        for (const tag of this.tags()) {
          this.getRowSelectionControl(tag.id).setValue(checked);
        }
      });
  }

  ngOnInit(): void {
    this.loadTags();
  }

  // ─── Actions ───────────────────────────────────────────────────────────────

  openCreate(): void {
    this.selectedTag.set(null);
    this.showFormModal.set(true);
  }

  openEdit(tag: Tag): void {
    this.selectedTag.set(tag);
    this.showFormModal.set(true);
  }

  openDelete(tag: Tag): void {
    this.tagToDelete.set(tag);
    this.showDeleteModal.set(true);
  }

  openBulkDelete(): void {
    if (!this.hasSelection()) return;
    this.tagToDelete.set(null);
    this.showDeleteModal.set(true);
  }

  handleFormSaved(tag: Tag): void {
    this.showFormModal.set(false);
    this.selectedTag.set(null);
    this.toast.success('Etiqueta salva com sucesso.');
    // Update item in place for immediate feedback, then reload for consistency
    this.tags.update((list) => {
      const exists = list.some((t) => t.id === tag.id);
      return exists ? list.map((t) => (t.id === tag.id ? tag : t)) : [tag, ...list];
    });
    this.loadTags();
  }

  handleFormCancelled(): void {
    this.showFormModal.set(false);
    this.selectedTag.set(null);
  }

  handleDeleteConfirmed(): void {
    if (this.isDeleting()) return;

    const singleTag = this.tagToDelete();
    const idsToDelete = singleTag ? [singleTag.id] : this.selectedTagIds();

    if (idsToDelete.length === 0) return;

    this.isDeleting.set(true);

    forkJoin(idsToDelete.map((id) => this.tagService.delete(id))).subscribe({
      next: () => {
        this.isDeleting.set(false);
        this.showDeleteModal.set(false);
        this.tagToDelete.set(null);
        this.clearSelection();
        this.toast.success(
          idsToDelete.length > 1
            ? 'Etiquetas excluídas com sucesso.'
            : 'Etiqueta excluída com sucesso.',
        );
        this.loadTags();
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
    this.loadTags();
  }

  loadPage(page: number): void {
    this.pageNumber.set(page);
    this.loadTags();
  }

  onSort(event: { field: string; dir: SortDirection }): void {
    this.sortBy.set(event.field);
    this.sortDir.set(event.dir);
    this.pageNumber.set(1);
    this.loadTags();
  }

  retry(): void {
    this.loadTags();
  }

  // ─── Data loading ──────────────────────────────────────────────────────────

  private loadTags(): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    const statusValue = this.filterStatusControl.value;
    const is_active =
      statusValue === 'active' ? true : statusValue === 'inactive' ? false : undefined;

    this.tagService
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
          this.tags.set(response.data);
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
      const selected = new Set(this.selectedTagIds());
      if (checked) {
        selected.add(id);
      } else {
        selected.delete(id);
      }

      this.selectedTagIds.set([...selected]);

      const allSelected =
        this.tags().length > 0 && this.tags().every((tag) => selected.has(tag.id));
      this.selectAllControl.setValue(allSelected, { emitEvent: false });
    });

    this.rowSelectionControls.set(id, control);
    return control;
  }

  private syncSelectionControls(tags: Tag[]): void {
    const activeIds = new Set(tags.map((tag) => tag.id));

    for (const key of this.rowSelectionControls.keys()) {
      if (!activeIds.has(key)) {
        this.rowSelectionControls.delete(key);
      }
    }

    const selected = this.selectedTagIds().filter((id) => activeIds.has(id));
    this.selectedTagIds.set(selected);

    for (const tag of tags) {
      this.getRowSelectionControl(tag.id).setValue(selected.includes(tag.id));
    }

    this.selectAllControl.setValue(tags.length > 0 && selected.length === tags.length, {
      emitEvent: false,
    });
  }

  private clearSelection(): void {
    this.selectedTagIds.set([]);
    this.selectAllControl.setValue(false, { emitEvent: false });
    for (const control of this.rowSelectionControls.values()) {
      control.setValue(false, { emitEvent: false });
    }
  }
}

export default Tags;
