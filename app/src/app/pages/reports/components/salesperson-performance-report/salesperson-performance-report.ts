import { DecimalPipe } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';
import { ChangeDetectionStrategy, Component } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';
import type { Observable } from 'rxjs';
import {
  AfButtonComponent,
  AfCardComponent,
  AfDrawerComponent,
  AfEmptyStateComponent,
  AfSkeletonComponent,
  AfPageTitleComponent,
  AfSearchInputComponent,
  AfSelectInputComponent,
  AfTextInputComponent,
  AfApexchartComponent,
} from '@shared/components';
import {
  type ReportFilter,
  type SalespersonReport,
  type ReportResponse,
} from '@shared/models/report.model';
import { formatCurrency } from '@shared/utils/currency';
import {
  BaseReportComponent,
  type NumericReportChartSeries,
} from '../../base/base-report.component';

/**
 * Componente de relatório de performance de vendedores.
 */
@Component({
  selector: 'app-salesperson-performance-report',
  standalone: true,
  imports: [
    DecimalPipe,
    ReactiveFormsModule,
    LucideAngularModule,
    AfButtonComponent,
    AfCardComponent,
    AfDrawerComponent,
    AfEmptyStateComponent,
    AfSkeletonComponent,
    AfPageTitleComponent,
    AfSearchInputComponent,
    AfSelectInputComponent,
    AfTextInputComponent,
    AfApexchartComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './salesperson-performance-report.html',
})
export class SalespersonPerformanceReportComponent extends BaseReportComponent<
  SalespersonReport,
  NumericReportChartSeries
> {
  readonly formatCurrency = formatCurrency;

  constructor() {
    super();
    this.setupReport();
  }

  protected override getReportKey(): string {
    return 'salesperson-performance';
  }

  protected override loadReportData(
    filters: ReportFilter,
  ): Observable<ReportResponse<SalespersonReport>> {
    return this.reportsService.getSalespersonPerformance(filters);
  }

  protected override onDataLoaded(data: SalespersonReport): void {
    this.buildChart(data);
  }

  private buildChart(report: SalespersonReport): void {
    const top5 = (report.salespersons ?? []).slice(0, 5);
    this.chartSeries.set(
      top5.map((s) => ({
        name: s.user_name,
        data: [s.won_count, s.win_rate, s.total_revenue / 1000, s.avg_ticket / 100, s.tasks_done],
      })),
    );
    this.chartCategories.set(['Ganhas', 'Taxa %', 'Receita (k)', 'Ticket (c)', 'Tarefas']);
    this.chartExtra.set({
      chart: { type: 'radar' },
      stroke: { width: 2 },
      fill: { opacity: 0.15 },
      markers: { size: 3 },
    });
  }
}
