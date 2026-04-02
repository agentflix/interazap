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
  type ContactCrmReport,
  type ReportResponse,
} from '@shared/models/report.model';
import { formatCurrency } from '@shared/utils/currency';
import {
  BaseReportComponent,
  type NumericReportChartSeries,
} from '../../base/base-report.component';

/**
 * Componente de relatório de contatos CRM.
 */
@Component({
  selector: 'app-contact-crm-report',
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
  templateUrl: './contact-crm-report.html',
})
export class ContactCrmReportComponent extends BaseReportComponent<
  ContactCrmReport,
  NumericReportChartSeries
> {
  // Expose formatCurrency for template use
  readonly formatCurrency = formatCurrency;

  constructor() {
    super();
    this.setupReport();
  }

  protected override getReportKey(): string {
    return 'contact-crm';
  }

  protected override loadReportData(
    filters: ReportFilter,
  ): Observable<ReportResponse<ContactCrmReport>> {
    return this.reportsService.getContactCrm(filters);
  }

  protected override onDataLoaded(data: ContactCrmReport): void {
    this.buildChart(data);
  }

  private buildChart(report: ContactCrmReport): void {
    const monthly = report.monthly_growth ?? [];
    this.chartSeries.set([{ name: 'Contatos', data: monthly.map((m) => m.count) }]);
    this.chartCategories.set(monthly.map((m) => m.month));
    this.chartExtra.set({
      stroke: { curve: 'smooth', width: 2 },
      markers: { size: 4 },
      dataLabels: { enabled: false },
      grid: { padding: { top: -10, bottom: -10 } },
    });
  }
}
