import { DecimalPipe } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';
import { ChangeDetectionStrategy, Component } from '@angular/core';
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
  AfApexchartComponent,
  AfTextInputComponent,
  AfReportLoadingComponent,
  AfReportErrorComponent,
  AfReportEmptyComponent,
} from '@shared/components';
import {
  type ReportFilter,
  type ChatVolumeReport,
  type ReportResponse,
} from '@shared/models/report.model';
import { BaseReportComponent } from '../../base/base-report.component';

/**
 * Componente de relatório de volume de atendimento.
 */
@Component({
  selector: 'app-chat-volume-report',
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
    AfApexchartComponent,
    AfTextInputComponent,
    AfReportLoadingComponent,
    AfReportErrorComponent,
    AfReportEmptyComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './chat-volume-report.html',
})
export class ChatVolumeReportComponent extends BaseReportComponent<ChatVolumeReport> {
  private readonly dayLabels = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

  constructor() {
    super();
    this.setupReport();
  }

  protected override getReportKey(): string {
    return 'chat-volume';
  }

  protected override loadReportData(
    filters: ReportFilter,
  ): Observable<ReportResponse<ChatVolumeReport>> {
    return this.reportsService.getChatVolume(filters);
  }

  protected override onDataLoaded(data: ChatVolumeReport): void {
    this.buildChart(data);
  }

  private buildChart(report: ChatVolumeReport): void {
    const heatmap = report.heatmap ?? [];
    const series = this.dayLabels.map((day, i) => ({
      name: day,
      data: (heatmap[i] ?? Array.from({ length: 24 }, () => 0)).map((val: number, h: number) => ({
        x: `${h}h`,
        y: val,
      })),
    }));
    this.chartSeries.set(series);
    this.chartExtra.set({
      chart: { type: 'heatmap' },
      dataLabels: { enabled: false },
      stroke: { width: 0 },
      plotOptions: { heatmap: { shadeIntensity: 0.5, radius: 2 } },
      grid: { padding: { top: -10, bottom: -10 } },
    });
  }
}
