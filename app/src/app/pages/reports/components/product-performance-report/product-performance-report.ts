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
import {
  BaseReportComponent,
  type NumericReportChartSeries,
} from '../../base/base-report.component';
import { formatCurrency } from '@shared/utils/currency';
import {
  type ReportFilter,
  type ReportResponse,
  type ProductReport,
} from '@shared/models/report.model';

/**
 * Componente de relatório de performance de produtos.
 */
@Component({
  selector: 'app-product-performance-report',
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
  templateUrl: './product-performance-report.html',
})
export class ProductPerformanceReportComponent extends BaseReportComponent<
  ProductReport,
  NumericReportChartSeries
> {
  readonly formatCurrency = formatCurrency;

  constructor() {
    super();
    this.setupReport();
  }

  protected override getReportKey(): string {
    return 'product-performance';
  }

  protected override loadReportData(
    filters: ReportFilter,
  ): Observable<ReportResponse<ProductReport>> {
    return this.reportsService.getProductPerformance(filters);
  }

  protected override onDataLoaded(data: ProductReport): void {
    this.buildChart(data);
  }

  private buildChart(report: ProductReport): void {
    const top10 = (report.products ?? [])
      .sort((a, b) => b.total_revenue - a.total_revenue)
      .slice(0, 10);
    this.chartSeries.set([{ name: 'Receita', data: top10.map((p) => p.total_revenue) }]);
    this.chartCategories.set(top10.map((p) => p.product_name));
    this.chartExtra.set({
      plotOptions: { bar: { borderRadius: 4, horizontal: true } },
      yaxis: { labels: { formatter: (val: number) => formatCurrency(val) } },
      dataLabels: { enabled: false },
      grid: { padding: { top: -10, bottom: -10 } },
    });
  }
}
