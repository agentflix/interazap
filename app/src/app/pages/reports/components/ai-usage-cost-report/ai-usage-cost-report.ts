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
  AfSkeletonComponent,
  AfPageTitleComponent,
  AfSearchInputComponent,
  AfSelectInputComponent,
  AfTextInputComponent,
  AfKpiCardComponent,
  AfApexchartComponent,
} from '@shared/components';
import {
  type ReportFilter,
  type AiUsageReport,
  type ReportResponse,
} from '@shared/models/report.model';
import { formatCurrency } from '@shared/utils/currency';
import { BaseReportComponent } from '../../base/base-report.component';

interface BarExtraOptions {
  [key: string]: object | string | number | boolean | null | undefined;
  plotOptions?: { bar?: { distributed?: boolean; borderRadius?: number } };
  dataLabels?: { enabled: boolean };
}

interface LineExtraOptions {
  [key: string]: object | string | number | boolean | null | undefined;
  xaxis?: { type: 'datetime' };
  stroke?: { curve: 'smooth'; width: number };
  yaxis?: { labels: { formatter: (val: number) => string } };
  dataLabels?: { enabled: boolean };
  grid?: { padding: { top: number; bottom: number } };
}

/**
 * Componente de relatório de custos de IA.
 */
@Component({
  selector: 'app-ai-usage-cost-report',
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
    AfKpiCardComponent,
    AfApexchartComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './ai-usage-cost-report.html',
})
export class AiUsageCostReportComponent extends BaseReportComponent<AiUsageReport> {
  readonly formatCurrency = formatCurrency;

  readonly treemapSeries = signal<{ name: string; data: number[] }[]>([]);
  readonly treemapCategories = signal<string[]>([]);
  readonly treemapExtra = signal<BarExtraOptions>({});

  readonly dailySeries = signal<{ name: string; data: number[] }[]>([]);
  readonly dailyCategories = signal<string[]>([]);
  readonly dailyExtra = signal<LineExtraOptions>({});

  constructor() {
    super();
    this.setupReport();
  }

  protected override getReportKey(): string {
    return 'ai-usage-cost';
  }

  protected override loadReportData(
    filters: ReportFilter,
  ): Observable<ReportResponse<AiUsageReport>> {
    return this.reportsService.getAiUsageCost(filters);
  }

  protected override onDataLoaded(data: AiUsageReport): void {
    this.buildChart(data);
  }

  private buildChart(report: AiUsageReport): void {
    const features = report.by_feature ?? [];
    this.treemapSeries.set([
      { name: 'Custo por Feature', data: features.map((f) => f.total_cost) },
    ]);
    this.treemapCategories.set(features.map((f) => f.feature));
    this.treemapExtra.set({
      plotOptions: { bar: { distributed: true, borderRadius: 4 } },
      dataLabels: { enabled: true },
    });

    const daily = report.daily_cost ?? [];
    this.dailySeries.set([{ name: 'Custo Diário', data: daily.map((d) => d.cost) }]);
    this.dailyCategories.set(daily.map((d) => d.date));
    this.dailyExtra.set({
      xaxis: { type: 'datetime' },
      stroke: { curve: 'smooth', width: 2 },
      yaxis: { labels: { formatter: (val: number) => formatCurrency(val) } },
      dataLabels: { enabled: false },
      grid: { padding: { top: -10, bottom: -10 } },
    });
  }
}
