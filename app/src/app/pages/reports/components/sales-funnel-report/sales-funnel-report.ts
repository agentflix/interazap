import { DecimalPipe } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';
import { ChangeDetectionStrategy, Component, signal } from '@angular/core';
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
  type SalesFunnelReport,
  type ReportResponse,
} from '@shared/models/report.model';
import { formatCurrency } from '@shared/utils/currency';
import {
  BaseReportComponent,
  type NumericReportChartSeries,
} from '../../base/base-report.component';

/**
 * Componente de relatório do funil de vendas.
 */
@Component({
  selector: 'app-sales-funnel-report',
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
  templateUrl: './sales-funnel-report.html',
})
export class SalesFunnelReportComponent extends BaseReportComponent<
  SalesFunnelReport,
  NumericReportChartSeries
> {
  readonly formatCurrency = formatCurrency;
  readonly chartColors = signal<string[]>([]);

  constructor() {
    super();
    this.setupReport();
  }

  protected override getReportKey(): string {
    return 'sales-funnel';
  }

  protected override loadReportData(
    filters: ReportFilter,
  ): Observable<ReportResponse<SalesFunnelReport>> {
    return this.reportsService.getSalesFunnel(filters);
  }

  protected override onDataLoaded(data: SalesFunnelReport): void {
    this.buildChart(data);
  }

  private buildChart(report: SalesFunnelReport): void {
    const steps = report.steps ?? [];
    this.chartSeries.set([{ name: 'Negociações', data: steps.map((s) => s.count) }]);
    this.chartCategories.set(steps.map((s) => s.step_name));
    this.chartColors.set(steps.map((s) => s.step_color || '#2b7fff'));
    this.chartExtra.set({
      plotOptions: { bar: { horizontal: true, distributed: true, barHeight: '60%' } },
      dataLabels: { enabled: true },
      legend: { show: false },
    });
  }
}
