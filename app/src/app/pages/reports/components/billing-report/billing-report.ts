import { DecimalPipe } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';
import { ChangeDetectionStrategy, Component, signal } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';
import type { Observable } from 'rxjs';
import {
  AfButtonComponent,
  AfCardComponent,
  AfDrawerComponent,
  AfKpiCardComponent,
  AfPageTitleComponent,
  AfSearchInputComponent,
  AfSelectInputComponent,
  AfSkeletonComponent,
  AfApexchartComponent,
  AfTextInputComponent,
  AfReportErrorComponent,
  AfReportEmptyComponent,
  AfReportSkeletonGridComponent,
} from '@shared/components';
import {
  type ReportFilter,
  type BillingReport,
  type ReportResponse,
} from '@shared/models/report.model';
import { formatCurrency } from '@shared/utils/currency';
import {
  BaseReportComponent,
  type NumericReportChartSeries,
} from '../../base/base-report.component';

/**
 * Componente de relatório de faturamento.
 */
@Component({
  selector: 'app-billing-report',
  standalone: true,
  imports: [
    DecimalPipe,
    ReactiveFormsModule,
    LucideAngularModule,
    AfButtonComponent,
    AfCardComponent,
    AfDrawerComponent,
    AfKpiCardComponent,
    AfPageTitleComponent,
    AfSearchInputComponent,
    AfSelectInputComponent,
    AfSkeletonComponent,
    AfApexchartComponent,
    AfTextInputComponent,
    AfReportErrorComponent,
    AfReportEmptyComponent,
    AfReportSkeletonGridComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './billing-report.html',
})
export class BillingReportComponent extends BaseReportComponent<
  BillingReport,
  NumericReportChartSeries
> {
  // Expose formatCurrency for template use
  readonly formatCurrency = formatCurrency;

  constructor() {
    super();
    this.setupReport();
  }

  protected override getReportKey(): string {
    return 'billing';
  }

  protected override loadReportData(
    filters: ReportFilter,
  ): Observable<ReportResponse<BillingReport>> {
    return this.reportsService.getBilling(filters);
  }

  protected override onDataLoaded(data: BillingReport): void {
    this.buildChart(data);
  }

  private buildChart(report: BillingReport): void {
    const monthly = report.monthly_revenue ?? [];
    this.chartSeries.set([{ name: 'Receita', data: monthly.map((m) => m.revenue) }]);
    this.chartCategories.set(monthly.map((m) => m.month));
    this.chartExtra.set({
      yaxis: { labels: { formatter: (val: number) => formatCurrency(val) } },
      stroke: { curve: 'smooth', width: 2 },
      fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
      dataLabels: { enabled: false },
      grid: { padding: { top: -10, bottom: -10 } },
    });
  }
}
