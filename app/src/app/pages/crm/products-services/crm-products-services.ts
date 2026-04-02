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
import { formatCurrency } from '@shared/utils/currency';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfCheckboxInputComponent,
  AfCrudPageComponent,
  AfConfirmModalComponent,
  AfDataTableComponent,
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
import {
  type ProductService,
  ProductServiceService,
} from '@core/services/crm-product-service.service';
import { ProductServiceFormComponent } from './components/product-service-form/crm-product-service-form';

/**
 * Products & Services CRM page — CRUD with type filter and detail panel.
 * Business logic preserved verbatim from source.
 */
@Component({
  selector: 'app-products-services',
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
    AfSortableHeaderComponent,
    AfStatusBadgeComponent,
    AfTableActionsComponent,
    AfAlertComponent,
    ProductServiceFormComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './crm-products-services.html',
})
export class ProductsServices implements OnInit {
  private readonly productServiceService = inject(ProductServiceService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);

  readonly productFormRef = viewChild<ProductServiceFormComponent>('productForm');
  readonly isFormSaving = computed(() => this.productFormRef()?.isSaving() ?? false);

  // ─── List state ────────────────────────────────────────────────────────────
  readonly items = signal<ProductService[]>([]);
  readonly isLoading = signal(true);
  readonly hasError = signal(false);
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });

  readonly searchTerm = signal('');
  private readonly pageNumber = signal(1);
  readonly sortBy = signal<string>('name');
  readonly sortDir = signal<SortDirection>('asc');

  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.items().length === 0,
  );

  // ─── Filters ───────────────────────────────────────────────────────────────
  readonly filterStatusControl = new FormControl<string>('all', { nonNullable: true });
  readonly filterStatusOptions: AfSelectOption[] = [
    { label: 'Todos', value: 'all' },
    { label: 'Ativos', value: 'active' },
    { label: 'Inativos', value: 'inactive' },
  ];

  readonly filterTypeControl = new FormControl<string>('all', { nonNullable: true });
  readonly filterTypeOptions: AfSelectOption[] = [
    { label: 'Todos', value: 'all' },
    { label: 'Produtos', value: 'product' },
    { label: 'Serviços', value: 'service' },
  ];

  // ─── Modal state ───────────────────────────────────────────────────────────
  readonly showFormModal = signal(false);
  readonly selectedItem = signal<ProductService | null>(null);
  readonly formModalTitle = computed(() =>
    this.selectedItem() ? 'Editar produto/serviço' : 'Novo produto/serviço',
  );

  readonly showDeleteModal = signal(false);
  readonly itemToDelete = signal<ProductService | null>(null);
  readonly isDeleting = signal(false);

  readonly selectAllControl = new FormControl<boolean>(false, { nonNullable: true });
  private readonly rowSelectionControls = new Map<string, FormControl<boolean>>();
  readonly selectedItemIds = signal<string[]>([]);
  readonly selectedCount = computed(() => this.selectedItemIds().length);
  readonly hasSelection = computed(() => this.selectedCount() > 0);

  readonly deleteModalTitle = computed(() =>
    this.itemToDelete() ? 'Excluir produto/serviço' : 'Excluir produtos/serviços',
  );
  readonly deleteConfirmLabel = computed(() =>
    this.itemToDelete() ? 'Excluir' : 'Excluir selecionados',
  );
  readonly deleteMessage = computed(() => {
    const i = this.itemToDelete();
    if (!i && this.selectedCount() > 0) {
      return `Tem certeza que deseja excluir ${this.selectedCount()} itens selecionados? Esta ação não pode ser desfeita.`;
    }
    return i
      ? `Tem certeza que deseja excluir "${i.name}"? Esta ação não pode ser desfeita.`
      : 'Tem certeza que deseja excluir este item?';
  });

  // ─── Details panel ─────────────────────────────────────────────────────────
  readonly detailItem = signal<ProductService | null>(null);
  readonly isDetailsOpen = signal(false);

  constructor() {
    this.filterStatusControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        this.pageNumber.set(1);
        this.loadItems();
      });

    this.filterTypeControl.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => {
      this.pageNumber.set(1);
      this.loadItems();
    });

    this.selectAllControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((checked) => {
        const next = checked ? this.items().map((i) => i.id) : [];
        this.selectedItemIds.set(next);
        for (const i of this.items()) {
          this.getRowSelectionControl(i.id).setValue(checked);
        }
      });
  }

  ngOnInit(): void {
    this.loadItems();
  }

  // ─── Actions ───────────────────────────────────────────────────────────────
  openCreate(): void {
    this.selectedItem.set(null);
    this.showFormModal.set(true);
  }

  openEdit(item: ProductService): void {
    this.selectedItem.set(item);
    this.showFormModal.set(true);
  }

  openDelete(item: ProductService): void {
    this.itemToDelete.set(item);
    this.showDeleteModal.set(true);
  }

  openBulkDelete(): void {
    if (!this.hasSelection()) return;
    this.itemToDelete.set(null);
    this.showDeleteModal.set(true);
  }

  openDetails(item: ProductService): void {
    this.detailItem.set(item);
    this.isDetailsOpen.set(true);
  }

  closeDetails(): void {
    this.isDetailsOpen.set(false);
  }

  handleFormSaved(_item: ProductService): void {
    this.showFormModal.set(false);
    this.selectedItem.set(null);
    this.toast.success('Item salvo com sucesso.');
    this.loadItems();
  }

  handleFormCancelled(): void {
    this.showFormModal.set(false);
    this.selectedItem.set(null);
  }

  handleDeleteConfirmed(): void {
    if (this.isDeleting()) return;
    const single = this.itemToDelete();
    const ids = single ? [single.id] : this.selectedItemIds();
    if (ids.length === 0) return;

    this.isDeleting.set(true);
    forkJoin(ids.map((id) => this.productServiceService.delete(id))).subscribe({
      next: () => {
        this.isDeleting.set(false);
        this.showDeleteModal.set(false);
        this.itemToDelete.set(null);
        this.clearSelection();
        this.toast.success(
          ids.length > 1 ? 'Itens excluídos com sucesso.' : 'Item excluído com sucesso.',
        );
        this.loadItems();
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
    this.loadItems();
  }

  loadPage(page: number): void {
    this.pageNumber.set(page);
    this.loadItems();
  }

  onSort(event: { field: string; dir: SortDirection }): void {
    this.sortBy.set(event.field);
    this.sortDir.set(event.dir);
    this.pageNumber.set(1);
    this.loadItems();
  }

  retry(): void {
    this.loadItems();
  }

  /** Format number as BRL currency */
  formatCurrency(value: number): string {
    return formatCurrency(value);
  }

  // ─── Data loading ──────────────────────────────────────────────────────────
  private loadItems(): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    const statusValue = this.filterStatusControl.value;
    const typeValue = this.filterTypeControl.value;

    const is_active =
      statusValue === 'active' ? true : statusValue === 'inactive' ? false : undefined;
    const type = typeValue === 'product' || typeValue === 'service' ? typeValue : undefined;

    this.productServiceService
      .list({
        search: this.searchTerm() || undefined,
        page: this.pageNumber(),
        per_page: 15,
        sort_by: this.sortBy(),
        sort_dir: this.sortDir(),
        is_active,
        type,
      })
      .subscribe({
        next: (response) => {
          this.items.set(response.data);
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
      const selected = new Set(this.selectedItemIds());
      if (checked) selected.add(id);
      else selected.delete(id);
      this.selectedItemIds.set([...selected]);

      const allSelected = this.items().length > 0 && this.items().every((i) => selected.has(i.id));
      this.selectAllControl.setValue(allSelected, { emitEvent: false });
    });

    this.rowSelectionControls.set(id, control);
    return control;
  }

  private syncSelectionControls(items: ProductService[]): void {
    const activeIds = new Set(items.map((i) => i.id));
    for (const key of this.rowSelectionControls.keys()) {
      if (!activeIds.has(key)) this.rowSelectionControls.delete(key);
    }
    const selected = this.selectedItemIds().filter((id) => activeIds.has(id));
    this.selectedItemIds.set(selected);
    for (const i of items) {
      this.getRowSelectionControl(i.id).setValue(selected.includes(i.id));
    }
    this.selectAllControl.setValue(items.length > 0 && selected.length === items.length, {
      emitEvent: false,
    });
  }

  private clearSelection(): void {
    this.selectedItemIds.set([]);
    this.selectAllControl.setValue(false, { emitEvent: false });
    for (const control of this.rowSelectionControls.values()) {
      control.setValue(false, { emitEvent: false });
    }
  }
}

export default ProductsServices;
