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
  AfKpiCardComponent,
  AfApexchartComponent,
} from '@shared/components';
import { BaseReportComponent } from '../../base/base-report.component';
import { formatCurrency } from '@shared/utils/currency';
import {
  type ReportFilter,
  type ReportResponse,
  type MediaTranscriptionReport,
} from '@shared/models/report.model';

interface BarExtraOptions extends Record<
  string,
  object | string | number | boolean | null | undefined
> {
  chart?: { type: 'bar' };
  plotOptions?: { bar?: { distributed?: boolean; borderRadius?: number } };
  dataLabels?: { enabled: boolean };
}

interface LineExtraOptions extends Record<
  string,
  object | string | number | boolean | null | undefined
> {
  xaxis?: { type: 'datetime' };
  stroke?: { curve: 'smooth'; width: number };
  yaxis?: { labels: { formatter: (val: number) => string } };
  dataLabels?: { enabled: boolean };
  grid?: { padding: { top: number; bottom: number } };
}

/**
 * Componente de relatório de transcrição de mídia.
 *
 * @description Exibe métricas de transcrição: total de transcrições, tokens, custo,
 * latência média, breakdown por tipo de mídia e modelo, e custo diário.
 */
@Component({
  selector: 'app-media-transcription-report',
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
  templateUrl: './media-transcription-report.html',
})
export class MediaTranscriptionReportComponent extends BaseReportComponent<MediaTranscriptionReport> {
  readonly formatCurrency = formatCurrency;
  readonly byTypeSeries = signal<{ name: string; data: number[] }[]>([]);
  readonly byTypeCategories = signal<string[]>([]);
  readonly byTypeExtra = signal<BarExtraOptions>({});

  readonly dailySeries = signal<{ name: string; data: number[] }[]>([]);
  readonly dailyCategories = signal<string[]>([]);
  readonly dailyExtra = signal<LineExtraOptions>({});

  constructor() {
    super();
    this.setupReport();
  }

  protected override getReportKey(): string {
    return 'media-transcription';
  }

  protected override loadReportData(
    filters: ReportFilter,
  ): Observable<ReportResponse<MediaTranscriptionReport>> {
    return this.reportsService.getMediaTranscription(filters);
  }

  protected override onDataLoaded(data: MediaTranscriptionReport): void {
    this.buildCharts(data);
  }

  private buildCharts(report: MediaTranscriptionReport): void {
    const typeLabels: Record<string, string> = {
      audio: 'Áudio',
      image: 'Imagem',
      video: 'Vídeo',
    };

    const types = report.by_type ?? [];
    this.byTypeCategories.set(types.map((t) => typeLabels[t.media_type] ?? t.media_type));
    this.byTypeSeries.set([{ name: 'Transcrições', data: types.map((t) => t.count) }]);
    this.byTypeExtra.set({
      chart: { type: 'bar' },
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
