import { DecimalPipe } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';
import { ChangeDetectionStrategy, Component, signal } from '@angular/core';
import type { Observable } from 'rxjs';
import { LucideAngularModule } from 'lucide-angular';
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
  AfKpiCardComponent,
} from '@shared/components';
import {
  BaseReportComponent,
  type NumericReportChartSeries,
} from '../../base/base-report.component';
import {
  type ReportFilter,
  type ReportResponse,
  type AutopilotReport,
} from '@shared/models/report.model';

/**
 * Componente de relatório de performance de automações.
 */
@Component({
  selector: 'app-autopilot-performance-report',
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
    AfKpiCardComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './autopilot-performance-report.html',
})
export class AutopilotPerformanceReportComponent extends BaseReportComponent<
  AutopilotReport,
  NumericReportChartSeries
> {
  constructor() {
    super();
    this.setupReport();
  }

  protected override getReportKey(): string {
    return 'autopilot-performance';
  }

  protected override loadReportData(
    filters: ReportFilter,
  ): Observable<ReportResponse<AutopilotReport>> {
    return this.reportsService.getAutopilotPerformance(filters);
  }

  protected override onDataLoaded(data: AutopilotReport): void {
    this.buildChart(data);
  }

  private buildChart(report: AutopilotReport): void {
    const daily = report.daily_runs ?? [];
    this.chartSeries.set([{ name: 'Execuções', data: daily.map((d) => d.count) }]);
    this.chartCategories.set(daily.map((d) => d.date));
    this.chartExtra.set({
      xaxis: { type: 'datetime' },
      plotOptions: { bar: { borderRadius: 4 } },
      dataLabels: { enabled: false },
      grid: { padding: { top: -10, bottom: -10 } },
    });
  }
}
