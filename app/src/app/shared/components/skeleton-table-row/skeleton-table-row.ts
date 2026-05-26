import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';
import { AfSkeletonComponent } from '../skeleton/skeleton';

/**
 * Linhas skeleton animadas para simular carregamento de dados em tabelas.
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
  /** Número de colunas por linha */
  readonly columns = input(4);

  /** Número de linhas skeleton */
  readonly rows = input(5);

  /** Gera array de índices de colunas */
  protected readonly columnsArray = computed(() =>
    Array.from({ length: this.columns() }, (_, i) => i),
  );

  /** Gera array de índices de linhas */
  protected readonly rowsArray = computed(() => Array.from({ length: this.rows() }, (_, i) => i));

  /** Larguras variadas de coluna para aparência mais natural */
  protected columnWidthClass(index: number): string {
    const widths = ['flex-1', 'w-1/6', 'flex-1', 'w-1/4', 'w-1/5', 'flex-1'];
    return widths[index % widths.length];
  }
}
