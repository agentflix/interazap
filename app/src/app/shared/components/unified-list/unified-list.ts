import {
  type TemplateRef,
  Component,
  ChangeDetectionStrategy,
  input,
  output,
  signal,
  computed,
  contentChild,
} from '@angular/core';
import { NgTemplateOutlet } from '@angular/common';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfButtonComponent } from '../button/button';
import { AfIconButtonComponent } from '../icon-button/icon-button';
import { AfSpinnerComponent } from '../spinner/spinner';
import { AfEmptyStateComponent } from '../empty-state/empty-state';
import { LucideAngularModule } from 'lucide-angular';

/**
 * AfUnifiedListComponent — Full-featured list container with search, filters,
 * create/delete actions, loading overlay, empty state and pagination.
 *
 * @example
 * ```html
 * <af-unified-list
 *   [isLoading]="loading()"
 *   [total]="meta().total"
 *   [currentPage]="meta().currentPage"
 *   [lastPage]="meta().lastPage"
 *   (searchChange)="onSearch($event)"
 *   (pageChange)="loadPage($event)"
 *   (createClick)="openCreate()"
 * >
 *   <ng-template #tableHeader>...</ng-template>
 *   <ng-template #tableBody>...</ng-template>
 * </af-unified-list>
 * ```
 */
@Component({
  selector: 'af-unified-list',
  standalone: true,
  imports: [
    NgTemplateOutlet,
    ReactiveFormsModule,
    AfButtonComponent,
    AfIconButtonComponent,
    AfSpinnerComponent,
    AfEmptyStateComponent,
    LucideAngularModule,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './unified-list.html',
})
export class AfUnifiedListComponent {
  /** Loading state */
  readonly isLoading = input(false);

  /** Total items */
  readonly total = input(0);

  /** Items per page */
  readonly perPage = input(10);

  /** Current page number */
  readonly currentPage = input(1);

  /** Last page number */
  readonly lastPage = input(1);

  /** Whether selection mode is active */
  readonly hasSelection = input(false);

  /** Number of selected items */
  readonly selectedCount = input(0);

  /** Search placeholder */
  readonly searchPlaceholder = input('Buscar por...');

  /** Create button label */
  readonly createLabel = input('Novo');

  /** Empty state title */
  readonly emptyTitle = input('Nenhum item encontrado');

  /** Empty state description */
  readonly emptyDescription = input('Tente ajustar os filtros ou criar um novo item.');

  /** Emitted when search text changes */
  readonly searchChange = output<string>();

  /** Emitted on page change */
  readonly pageChange = output<number>();

  /** Emitted on filter button click */
  readonly filterClick = output<void>();

  /** Emitted on create button click */
  readonly createClick = output<void>();

  /** Emitted on delete selected click */
  readonly deleteSelectedClick = output<void>();

  /** Content-projected table header template */
  readonly headerTpl = contentChild.required<TemplateRef<unknown>>('tableHeader');

  /** Content-projected table body template */
  readonly bodyTpl = contentChild.required<TemplateRef<unknown>>('tableBody');

  protected readonly searchControl = new FormControl('', { nonNullable: true });

  protected readonly isEmpty = computed(() => this.total() === 0);
  protected readonly showingFrom = computed(() =>
    this.total() === 0 ? 0 : (this.currentPage() - 1) * this.perPage() + 1,
  );
  protected readonly showingTo = computed(() =>
    Math.min(this.currentPage() * this.perPage(), this.total()),
  );
  protected readonly pages = computed(() => {
    const pages: number[] = [];
    for (let i = 1; i <= this.lastPage(); i++) {
      pages.push(i);
    }
    return pages.length > 7 ? this.paginateRange(this.currentPage(), this.lastPage()) : pages;
  });

  constructor() {
    this.searchControl.valueChanges.subscribe((v) => this.searchChange.emit(v));
  }

  private paginateRange(current: number, last: number): number[] {
    const delta = 2;
    const range: number[] = [];
    for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
      range.push(i);
    }
    if (current - delta > 2) range.unshift(-1);
    if (current + delta < last - 1) range.push(-1);
    range.unshift(1);
    if (last > 1) range.push(last);
    return range;
  }
}
