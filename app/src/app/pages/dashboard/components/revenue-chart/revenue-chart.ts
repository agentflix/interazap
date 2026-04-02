import { ChangeDetectionStrategy, Component, input, computed } from '@angular/core';
import { AfApexchartComponent, AfCardComponent, AfEmptyStateComponent } from '@shared/components';
import { type RevenueMonth } from '../../models/dashboard.model';

/**
 * Revenue chart — stacked bar chart showing won vs open revenue by month.
 * Business logic preserved verbatim from source.
 */
@Component({
  selector: 'app-revenue-chart',
  standalone: true,
  imports: [AfApexchartComponent, AfCardComponent, AfEmptyStateComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { class: 'block' },
  templateUrl: './revenue-chart.html',
})
export class RevenueChartComponent {
  readonly data = input.required<RevenueMonth[]>();

  protected readonly hasData = computed(() => (this.data() ?? []).length > 0);

  protected readonly labels = computed(() =>
    (this.data() ?? []).map((item) => this.formatLabel(item)),
  );

  protected readonly series = computed(() => [
    { name: 'Ganhas', data: (this.data() ?? []).map((item) => item.won_amount) },
    { name: 'Em aberto', data: (this.data() ?? []).map((item) => item.open_amount) },
  ]);

  protected readonly chartOptions = {
    chart: { stacked: true, stackType: '100%', parentHeightOffset: 0 },
    plotOptions: { bar: { horizontal: false, columnWidth: '50%' } },
    fill: { opacity: 1 },
    grid: { show: true, padding: { top: -20, right: -10, bottom: -10 } },
    legend: { position: 'bottom' },
  };

  private formatLabel(item: RevenueMonth): string {
    const months = [
      'Jan',
      'Fev',
      'Mar',
      'Abr',
      'Mai',
      'Jun',
      'Jul',
      'Ago',
      'Set',
      'Out',
      'Nov',
      'Dez',
    ];
    const monthLabel = months[item.month - 1] ?? 'N/A';
    const yearLabel = String(item.year).slice(-2);
    return `${monthLabel}/${yearLabel}`;
  }
}

export default RevenueChartComponent;
