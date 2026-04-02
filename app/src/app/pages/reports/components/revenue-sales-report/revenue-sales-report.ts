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
  AfKpiCardComponent,
  AfPageTitleComponent,
  AfSearchInputComponent,
  AfSelectInputComponent,
  AfSkeletonComponent,
  AfApexchartComponent,
  AfTextInputComponent,
} from '@shared/components';
import {
  type ReportFilter,
  type RevenueSalesReport,
  type ReportResponse,
} from '@shared/models/report.model';
import { formatCurrency } from '@shared/utils/currency';
import {
  BaseReportComponent,
  type NumericReportChartSeries,
} from '../../base/base-report.component';

/**
 * Componente de relatório de receita e vendas.
 */
@Component({
  selector: 'app-revenue-sales-report',
  standalone: true,
  imports: [
    DecimalPipe,
    ReactiveFormsModule,
    LucideAngularModule,
    AfButtonComponent,
    AfCardComponent,
    AfDrawerComponent,
    AfEmptyStateComponent,
    AfKpiCardComponent,
    AfPageTitleComponent,
    AfSearchInputComponent,
    AfSelectInputComponent,
    AfSkeletonComponent,
    AfApexchartComponent,
    AfTextInputComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './revenue-sales-report.html',
})
export class RevenueSalesReportComponent extends BaseReportComponent<
  RevenueSalesReport,
  NumericReportChartSeries
> {
  // Expose formatCurrency for template use
  readonly formatCurrency = formatCurrency;

  constructor() {
    super();
    this.setupReport();
  }

  protected override getReportKey(): string {
    return 'revenue-sales';
  }

  protected override loadReportData(
    filters: ReportFilter,
  ): Observable<ReportResponse<RevenueSalesReport>> {
    return this.reportsService.getRevenueSales(filters);
  }

  protected override onDataLoaded(data: RevenueSalesReport): void {
    this.buildChart(data);
  }

  private buildChart(report: RevenueSalesReport): void {
    const series = report.time_series ?? [];
    this.chartSeries.set([{ name: 'Receita', data: series.map((s) => s.revenue) }]);
    this.chartCategories.set(series.map((s) => s.date));
    this.chartExtra.set({
      stroke: { curve: 'smooth', width: 2 },
      fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
      dataLabels: { enabled: false },
    });
  }
}
