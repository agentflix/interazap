import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';
import { AfCardComponent } from '../card/card';
import { AfSkeletonComponent } from '../skeleton/skeleton';

export type ReportLoadingLayout = 'kpi+chart' | 'table' | 'kpi+table' | 'chart';

/**
 * AfReportLoadingComponent — Skeleton loading state for report pages.
 *
 * Renders appropriate skeleton grids based on the layout type.
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
  /** Number of KPI skeleton cards to render */
  readonly kpiCount = input(4);

  /** Layout pattern determining which skeleton sections to show */
  readonly layout = input<ReportLoadingLayout>('kpi+chart');

  /** Number of table row skeletons to render */
  readonly tableRows = input(5);

  /** Array for KPI skeleton iteration */
  protected readonly kpiItems = computed(() => Array.from({ length: this.kpiCount() }));

  /** Array for table row skeleton iteration */
  protected readonly tableRowItems = computed(() => Array.from({ length: this.tableRows() }));

  protected getKpiCols(count: number): string {
    if (count >= 6) return 'grid-cols-1 sm:grid-cols-3 lg:grid-cols-6';
    if (count >= 5) return 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-5';
    if (count >= 3) return 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4';
    return 'grid-cols-1 sm:grid-cols-2';
  }
}
