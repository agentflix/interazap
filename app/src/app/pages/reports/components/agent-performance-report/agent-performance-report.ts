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
  AfSkeletonComponent,
  AfPageTitleComponent,
  AfSearchInputComponent,
  AfSelectInputComponent,
  AfTextInputComponent,
} from '@shared/components';
import {
  type ReportFilter,
  type AgentPerformanceReport,
  type ReportResponse,
} from '@shared/models/report.model';
import { formatCurrency } from '@shared/utils/currency';
import { BaseReportComponent } from '../../base/base-report.component';

/**
 * Componente de relatório de performance de agentes.
 */
@Component({
  selector: 'app-agent-performance-report',
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
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './agent-performance-report.html',
})
export class AgentPerformanceReportComponent extends BaseReportComponent<AgentPerformanceReport> {
  // Expose formatCurrency for template use
  readonly formatCurrency = formatCurrency;

  constructor() {
    super();
    this.setupReport();
  }

  protected override getReportKey(): string {
    return 'agent-performance';
  }

  protected override loadReportData(
    filters: ReportFilter,
  ): Observable<ReportResponse<AgentPerformanceReport>> {
    return this.reportsService.getAgentPerformance(filters);
  }
}
