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
} from '@shared/components';
import { BaseReportComponent } from '../../base/base-report.component';
import { formatCurrency } from '@shared/utils/currency';
import {
  type ReportFilter,
  type ReportResponse,
  type LossReasonReport,
} from '@shared/models/report.model';

/**
 * Componente de relatório de motivos de perda.
 */
@Component({
  selector: 'app-loss-reason-report',
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
  templateUrl: './loss-reason-report.html',
})
export class LossReasonReportComponent extends BaseReportComponent<LossReasonReport> {
  readonly formatCurrency = formatCurrency;
  readonly chartSeriesNonAxis = signal<number[]>([]);
  readonly chartLabels = signal<string[]>([]);

  constructor() {
    super();
    this.setupReport();
  }

  protected override getReportKey(): string {
    return 'loss-reasons';
  }

  protected override loadReportData(
    filters: ReportFilter,
  ): Observable<ReportResponse<LossReasonReport>> {
    return this.reportsService.getLossReasons(filters);
  }

  protected override onDataLoaded(data: LossReasonReport): void {
    this.buildChart(data);
  }

  private buildChart(report: LossReasonReport): void {
    const reasons = report.reasons ?? [];
    this.chartSeriesNonAxis.set(reasons.map((r) => r.count));
    this.chartLabels.set(reasons.map((r) => r.reason_name));
    this.chartExtra.set({
      legend: { position: 'bottom' },
      plotOptions: { pie: { donut: { size: '55%' } } },
      dataLabels: { enabled: true, formatter: (val: number) => `${val.toFixed(1)}%` },
    });
  }
}
