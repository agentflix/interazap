import { ReactiveFormsModule } from '@angular/forms';
import { ChangeDetectionStrategy, Component } from '@angular/core';
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
  AfTextInputComponent,
} from '@shared/components';
import {
  type ReportFilter,
  type TeamActivityReport,
  type ReportResponse,
} from '@shared/models/report.model';
import { BaseReportComponent } from '../../base/base-report.component';

/**
 * Componente de relatório de atividade da equipe.
 */
@Component({
  selector: 'app-team-activity-report',
  standalone: true,
  imports: [
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
    AfTextInputComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './team-activity-report.html',
})
export class TeamActivityReportComponent extends BaseReportComponent<TeamActivityReport> {
  constructor() {
    super();
    this.setupReport();
  }

  protected override getReportKey(): string {
    return 'team-activity';
  }

  protected override loadReportData(
    filters: ReportFilter,
  ): Observable<ReportResponse<TeamActivityReport>> {
    return this.reportsService.getTeamActivity(filters);
  }
}
