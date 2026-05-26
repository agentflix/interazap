import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';
import { AfCardComponent } from '../card/card';
import { AfSkeletonComponent } from '../skeleton/skeleton';

export type ReportLoadingLayout = 'kpi+chart' | 'table' | 'kpi+table' | 'chart';

/**
 * Estado de carregamento skeleton para páginas de relatório.
 *
 * Renderiza grids de skeleton apropriados ao tipo de layout.
 *
 * @example
 * ```html
 * <af-report-loading kpiCount="6" layout="kpi+chart" />
 * ```
 */
@Component({
  selector: 'af-report-loading',
  standalone: true,
  imports: [AfCardComponent, AfSkeletonComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './report-loading.html',
})
export class AfReportLoadingComponent {
  /** Número de cards KPI skeleton a renderizar */
  readonly kpiCount = input(4);

  /** Padrão de layout que determina quais seções skeleton exibir */
  readonly layout = input<ReportLoadingLayout>('kpi+chart');

  /** Número de linhas skeleton de tabela a renderizar */
  readonly tableRows = input(5);

  /** Array para iteração dos skeletons de KPI */
  protected readonly kpiItems = computed(() => Array.from({ length: this.kpiCount() }));

  /** Array para iteração das linhas skeleton da tabela */
  protected readonly tableRowItems = computed(() => Array.from({ length: this.tableRows() }));

  protected getKpiCols(count: number): string {
    if (count >= 6) return 'grid-cols-1 sm:grid-cols-3 lg:grid-cols-6';
    if (count >= 5) return 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-5';
    if (count >= 3) return 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4';
    return 'grid-cols-1 sm:grid-cols-2';
  }
}
