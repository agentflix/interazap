import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';
import { AfCardComponent } from '../card/card';
import { AfSkeletonComponent } from '../skeleton/skeleton';

/**
 * Grid de skeletons para seções KPI de relatórios.
 *
 * Renderiza uma grade responsiva de cards skeleton com dimensões equivalentes ao KPI card.
 *
 * @example
 * ```html
 * <af-report-skeleton-grid count="6" />
 * <af-report-skeleton-grid count="4" smCols="2" lgCols="4" />
 * ```
 */
@Component({
  selector: 'af-report-skeleton-grid',
  standalone: true,
  imports: [AfCardComponent, AfSkeletonComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './report-skeleton-grid.html',
})
export class AfReportSkeletonGridComponent {
  /** Número de cards skeleton a renderizar */
  readonly count = input(4);

  /** Colunas do grid no breakpoint sm (padrão: detecção automática pelo count) */
  readonly smCols = input<number | null>(null);

  /** Colunas do grid no breakpoint lg (padrão: detecção automática pelo count) */
  readonly lgCols = input<number | null>(null);

  /** Array para iteração dos cards skeleton */
  protected readonly items = computed(() => Array.from({ length: this.count() }));

  protected readonly gridClasses = computed(() => {
    const sm = this.smCols() ?? this.defaultSmCols();
    const lg = this.lgCols() ?? this.defaultLgCols();

    const smMap: Record<number, string> = {
      1: 'sm:grid-cols-1',
      2: 'sm:grid-cols-2',
      3: 'sm:grid-cols-3',
      4: 'sm:grid-cols-4',
      5: 'sm:grid-cols-5',
      6: 'sm:grid-cols-6',
    };
    const lgMap: Record<number, string> = {
      1: 'lg:grid-cols-1',
      2: 'lg:grid-cols-2',
      3: 'lg:grid-cols-3',
      4: 'lg:grid-cols-4',
      5: 'lg:grid-cols-5',
      6: 'lg:grid-cols-6',
    };

    return `grid-cols-1 ${smMap[sm] ?? 'sm:grid-cols-2'} ${lgMap[lg] ?? 'lg:grid-cols-4'}`;
  });

  private defaultSmCols(): number {
    const count = this.count();
    if (count >= 6) return 3;
    if (count >= 3) return 2;
    return 1;
  }

  private defaultLgCols(): number {
    const count = this.count();
    if (count >= 6) return 6;
    if (count >= 5) return 5;
    if (count >= 3) return 4;
    return count;
  }
}
