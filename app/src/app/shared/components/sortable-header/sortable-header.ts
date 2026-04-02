import { Component, ChangeDetectionStrategy, input, output, computed } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';
import { type SortDirection } from '../crud-page/models/index';

/**
 * AfSortableHeaderComponent — Reusable sortable table header cell.
 *
 * Renders a `<th>` with click-to-sort and a visual indicator
 * showing the current sort direction. Designed to be used inside
 * `<thead>` rows of `af-data-table`.
 *
 * @example
 * ```html
 * <th
 *   afSortableHeader
 *   label="Nome"
 *   field="name"
 *   [currentField]="sortBy()"
 *   [currentDir]="sortDir()"
 *   (sortChange)="onSort($event)"
 * ></th>
 * ```
 */
@Component({
  // eslint-disable-next-line @angular-eslint/component-selector
  selector: '[afSortableHeader]',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: {
    role: 'columnheader',
    '[attr.aria-sort]': 'ariaSort()',
    '[class]': 'hostClasses()',
  },
  templateUrl: './sortable-header.html',
})
export class AfSortableHeaderComponent {
  /** Column display label */
  readonly label = input.required<string>();

  /** Field name sent to the API */
  readonly field = input.required<string>();

  /** Currently active sort field */
  readonly currentField = input<string>('');

  /** Currently active sort direction */
  readonly currentDir = input<SortDirection>('asc');

  /** Extra CSS classes for the host `<th>` */
  readonly class = input<string>('');

  /** Emitted when user toggles sort — payload is `{ field, dir }` */
  readonly sortChange = output<{ field: string; dir: SortDirection }>();

  /** Whether this column is the active sort target */
  protected readonly isActive = computed(() => this.currentField() === this.field());

  /** Host element classes */
  protected readonly hostClasses = computed(() => {
    const base = 'px-3.5 py-2 text-start text-sm font-medium';
    const extra = this.class();
    return extra ? `${base} ${extra}` : base;
  });

  /** ARIA sort attribute value */
  protected readonly ariaSort = computed(() => {
    if (!this.isActive()) return 'none';
    return this.currentDir() === 'asc' ? 'ascending' : 'descending';
  });

  /** Up chevron highlight */
  protected readonly upIconClasses = computed(() =>
    this.isActive() && this.currentDir() === 'asc'
      ? 'text-primary'
      : 'text-neutral-300 dark:text-neutral-600',
  );

  /** Down chevron highlight */
  protected readonly downIconClasses = computed(() =>
    this.isActive() && this.currentDir() === 'desc'
      ? 'text-primary'
      : 'text-neutral-300 dark:text-neutral-600',
  );

  /** Toggle sort direction: asc → desc → asc */
  toggleSort(): void {
    const nextDir: SortDirection = this.isActive() && this.currentDir() === 'asc' ? 'desc' : 'asc';
    this.sortChange.emit({ field: this.field(), dir: nextDir });
  }
}
