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
  type SlaReport,
  type ReportResponse,
} from '@shared/models/report.model';
import { BaseReportComponent } from '../../base/base-report.component';

/**
 * Componente de relatório de SLA e tempo de resolução.
 */
@Component({
  selector: 'app-sla-resolution-report',
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
  templateUrl: './sla-resolution-report.html',
})
export class SlaResolutionReportComponent extends BaseReportComponent<SlaReport> {
  readonly chartSeriesNonAxis = signal<number[]>([]);
  readonly chartLabels = signal<string[]>([]);
  readonly chartColors = signal<string[]>([]);

  constructor() {
    super();
    this.setupReport();
  }

  protected override getReportKey(): string {
    return 'sla-resolution';
  }

  protected override loadReportData(filters: ReportFilter): Observable<ReportResponse<SlaReport>> {
    return this.reportsService.getSlaResolution(filters);
  }

  protected override onDataLoaded(data: SlaReport): void {
    this.buildChart(data);
  }

  private buildChart(report: SlaReport): void {
    this.chartSeriesNonAxis.set([
      report.summary?.sla_first_response_rate ?? 0,
      report.summary?.sla_resolution_rate ?? 0,
    ]);
    this.chartLabels.set(['SLA 1ª Resposta', 'SLA Resolução']);
    this.chartColors.set(['#2b7fff', '#10b981']);
    this.chartExtra.set({
      chart: { type: 'radialBar' },
      plotOptions: {
        radialBar: {
          hollow: { size: '40%' },
          dataLabels: {
            name: { fontSize: '14px' },
            value: { fontSize: '20px', formatter: (val: number) => `${val.toFixed(1)}%` },
          },
        },
      },
    });
  }
}
