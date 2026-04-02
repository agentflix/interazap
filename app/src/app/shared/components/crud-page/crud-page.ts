import {
  Component,
  ChangeDetectionStrategy,
  input,
  output,
  DestroyRef,
  inject,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfPageTitleComponent } from '../page-title/page-title';
import { AfSearchInputComponent } from '../search-input/search-input';
import { AfEmptyStateComponent } from '../empty-state/empty-state';
import { AfPaginationComponent } from '../pagination/pagination';
import { AfSkeletonTableRowComponent } from '../skeleton-table-row/skeleton-table-row';
import { AfButtonComponent } from '../button/button';
import { LucideAngularModule } from 'lucide-angular';

/**
 * AfCrudPageComponent — Standard CRUD listing layout that orchestrates
 * a page title, search bar, content slot, pagination, and empty state.
 *
 * @example
 * ```html
 * <af-crud-page
 *   title="Contatos"
 *   subtitle="Gerencie seus contatos"
 *   createLabel="Novo Contato"
 *   [loading]="isLoading()"
 *   [empty]="contacts().length === 0"
 *   [currentPage]="meta.current_page"
 *   [lastPage]="meta.last_page"
 *   [perPage]="meta.per_page"
 *   [total]="meta.total"
 *   (create)="openCreateModal()"
 *   (search)="onSearch($event)"
 *   (pageChange)="loadPage($event)"
 * >
 *   <table class="w-full">...</table>
 * </af-crud-page>
 * ```
 */
@Component({
  selector: 'af-crud-page, app-crud-page',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    AfPageTitleComponent,
    AfSearchInputComponent,
    AfEmptyStateComponent,
    AfPaginationComponent,
    AfSkeletonTableRowComponent,
    AfButtonComponent,
    LucideAngularModule,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './crud-page.html',
})
export class AfCrudPageComponent<TItem = unknown> {
  /** Page heading */
  readonly title = input.required<string>();

  /** Optional subtitle */
  readonly subtitle = input<string | null>(null);

  /** Label for the create button (omit to hide) */
  readonly createLabel = input<string>();

  /** Search placeholder text */
  readonly searchPlaceholder = input('Buscar...');

  /** Optional search input label */
  readonly searchLabel = input<string>();

  /** Whether data is currently loading */
  readonly loading = input(false);

  /** Whether the data set is empty (after loading) */
  readonly empty = input(false);

  /** Empty-state heading */
  readonly emptyTitle = input('Nenhum registro encontrado');

  /** Empty-state description */
  readonly emptyDescription = input('Crie seu primeiro registro para começar.');

  /** Number of skeleton columns */
  readonly skeletonColumns = input(4);

  /** Number of skeleton rows */
  readonly skeletonRows = input(5);

  /** Current page (1-indexed) */
  readonly currentPage = input(1);

  /** Last available page */
  readonly lastPage = input(1);

  /** Items per page */
  readonly perPage = input(15);

  /** Total number of records */
  readonly total = input(0);

  /** Legacy CRUD service input */
  readonly service = input<unknown>();

  /** Legacy CRUD config input */
  readonly config = input<unknown>();

  /** Legacy columns input */
  readonly columns = input<unknown>();

  /** Legacy actions input */
  readonly actions = input<unknown>();

  /** Emitted when the create button is clicked */
  readonly create = output<void>();

  /** Emitted when a search term changes */
  // eslint-disable-next-line @angular-eslint/no-output-native
  readonly search = output<string>();

  /** Emitted when user navigates to a different page */
  readonly pageChange = output<number>();

  /** Legacy custom action output */
  readonly customAction = output<{ action: string; item: TItem }>();

  /** Internal search control */
  protected readonly searchControl = new FormControl('', { nonNullable: true });

  /** Legacy temporary status control */
  readonly tempStatusControl = new FormControl<string>('');

  private readonly destroyRef = inject(DestroyRef);

  constructor() {
    this.searchControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.search.emit(value));
  }

  openCreate(): void {
    this.create.emit();
  }

  handleFormSaved(_item: TItem): void {}

  closeModal(): void {}

  reloadList(): void {}

  goToPage(page: number): void {
    this.pageChange.emit(page);
  }

  showError(_message: string): void {}

  showSuccess(_message: string): void {}
}

export { AfCrudPageComponent as CrudPageComponent };
