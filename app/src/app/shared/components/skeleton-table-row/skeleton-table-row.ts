import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';
import { AfSkeletonComponent } from '../skeleton/skeleton';

/**
 * AfSkeletonTableRowComponent — Renders animated skeleton rows to simulate
 * table data loading.
 *
 * @example
 * ```html
 * <af-skeleton-table-row [columns]="5" [rows]="8" />
 * ```
 */
@Component({
  selector: 'af-skeleton-table-row',
  standalone: true,
  imports: [AfSkeletonComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './skeleton-table-row.html',
})
export class AfSkeletonTableRowComponent {
  /** Number of columns to render per row */
  readonly columns = input(4);

  /** Number of skeleton rows to render */
  readonly rows = input(5);

  /** Generate an array of column indices */
  protected readonly columnsArray = computed(() =>
    Array.from({ length: this.columns() }, (_, i) => i),
  );

  /** Generate an array of row indices */
  protected readonly rowsArray = computed(() => Array.from({ length: this.rows() }, (_, i) => i));

  /** Vary column widths for a more natural skeleton look */
  protected columnWidthClass(index: number): string {
    const widths = ['flex-1', 'w-1/6', 'flex-1', 'w-1/4', 'w-1/5', 'flex-1'];
    return widths[index % widths.length];
  }
}
