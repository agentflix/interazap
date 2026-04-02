import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * AfGridComponent — CSS grid layout helper.
 *
 * @example
 * ```html
 * <af-grid [cols]="3" gap="md">
 *   <div>Col 1</div>
 *   <div>Col 2</div>
 *   <div>Col 3</div>
 * </af-grid>
 * ```
 */
@Component({
  selector: 'af-grid',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './grid.html',
})
export class AfGridComponent {
  /** Number of columns (1-6) */
  readonly cols = input(3);

  /** Gap size */
  readonly gap = input<'none' | 'sm' | 'md' | 'lg'>('md');

  protected readonly gridClasses = computed(() => {
    const colMap: Record<number, string> = {
      1: 'grid-cols-1',
      2: 'grid-cols-1 sm:grid-cols-2',
      3: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
      4: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
      5: 'grid-cols-1 sm:grid-cols-3 lg:grid-cols-5',
      6: 'grid-cols-1 sm:grid-cols-3 lg:grid-cols-6',
    };
    const gapMap: Record<string, string> = { none: 'gap-0', sm: 'gap-2', md: 'gap-4', lg: 'gap-6' };
    return `grid ${colMap[this.cols()] ?? 'grid-cols-3'} ${gapMap[this.gap()]}`;
  });
}
