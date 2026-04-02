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
  type CsatNpsReport,
  type ReportResponse,
} from '@shared/models/report.model';
import { BaseReportComponent } from '../../base/base-report.component';

interface BarExtraOptions {
  [key: string]: object | string | number | boolean | null | undefined;
  plotOptions?: { bar?: { borderRadius?: number } };
  dataLabels?: { enabled: boolean };
  grid?: { padding: { top: number; bottom: number } };
}

interface LineExtraOptions {
  [key: string]: object | string | number | boolean | null | undefined;
  xaxis?: { type: 'datetime' };
  stroke?: { curve: 'smooth'; width: number };
  dataLabels?: { enabled: boolean };
  grid?: { padding: { top: number; bottom: number } };
}

/**
 * Componente de relatório de CSAT / NPS.
 */
@Component({
  selector: 'app-csat-nps-report',
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
  templateUrl: './csat-nps-report.html',
})
export class CsatNpsReportComponent extends BaseReportComponent<CsatNpsReport> {
  readonly distSeries = signal<{ name: string; data: number[] }[]>([]);
  readonly distCategories = signal<string[]>([]);
  readonly distExtra = signal<BarExtraOptions>({});

  readonly tsSeries = signal<{ name: string; data: number[] }[]>([]);
  readonly tsCategories = signal<string[]>([]);
  readonly tsExtra = signal<LineExtraOptions>({});

  constructor() {
    super();
    this.setupReport();
  }

  protected override getReportKey(): string {
    return 'csat-nps';
  }

  protected override loadReportData(
    filters: ReportFilter,
  ): Observable<ReportResponse<CsatNpsReport>> {
    return this.reportsService.getCsatNps(filters);
  }

  protected override onDataLoaded(data: CsatNpsReport): void {
    this.buildChart(data);
  }

  private buildChart(report: CsatNpsReport): void {
    const dist = report.distribution ?? {};
    const keys = Object.keys(dist).sort();
    this.distSeries.set([
      { name: 'Avaliações', data: keys.map((k) => (dist as Record<string, number>)[k]) },
    ]);
    this.distCategories.set(keys.map((k) => `${k} ★`));
    this.distExtra.set({
      plotOptions: { bar: { borderRadius: 4 } },
      dataLabels: { enabled: true },
      grid: { padding: { top: -10, bottom: -10 } },
    });

    const ts = report.time_series ?? [];
    this.tsSeries.set([{ name: 'CSAT Médio', data: ts.map((t) => t.csat_avg) }]);
    this.tsCategories.set(ts.map((t) => t.date));
    this.tsExtra.set({
      xaxis: { type: 'datetime' },
      stroke: { curve: 'smooth', width: 2 },
      dataLabels: { enabled: false },
      grid: { padding: { top: -10, bottom: -10 } },
    });
  }
}
