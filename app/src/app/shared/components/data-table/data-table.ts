import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * AfDataTableComponent — Styled table wrapper with standard row/column styling.
 * Use ng-content to project thead and tbody.
 *
 * @example
 * ```html
 * <af-data-table [striped]="true" [hoverable]="true">
 *   <thead><tr><th>Name</th><th>Email</th></tr></thead>
 *   <tbody><tr><td>João</td><td>j&#64;x.com</td></tr></tbody>
 * </af-data-table>
 * ```
 */
@Component({
  selector: 'af-data-table',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './data-table.html',
  styleUrl: './data-table.scss',
})
export class AfDataTableComponent {
  /** Zebra striping */
  readonly striped = input(false);

  /** Hover highlight */
  readonly hoverable = input(true);

  /** Compact mode */
  readonly compact = input(false);

  protected readonly tableClasses = computed(() => {
    const base = 'min-w-full divide-y divide-neutral-200 dark:divide-neutral-700';
    return base;
  });
}
