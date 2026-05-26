import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * Auxiliar de layout CSS grid responsivo.
 *
 * @example
 * ```html
 * <af-grid [cols]="3" gap="md">
 *   <div>Coluna 1</div>
 *   <div>Coluna 2</div>
 *   <div>Coluna 3</div>
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
  /** Número de colunas (1-6) */
  readonly cols = input(3);

  /** Tamanho do espaçamento entre itens */
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
