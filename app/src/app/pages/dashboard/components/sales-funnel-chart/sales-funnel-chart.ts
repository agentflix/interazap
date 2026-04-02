import { ChangeDetectionStrategy, Component, input, computed } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { AfApexchartComponent, AfCardComponent, AfEmptyStateComponent } from '@shared/components';
import { type FunnelStep } from '../../models/dashboard.model';

/**
 * Sales funnel chart — horizontal bar chart with step breakdown.
 * Business logic preserved verbatim from source.
 */
@Component({
  selector: 'app-sales-funnel-chart',
  standalone: true,
  imports: [AfApexchartComponent, AfCardComponent, AfEmptyStateComponent, CurrencyPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { class: 'block' },
  templateUrl: './sales-funnel-chart.html',
})
export class SalesFunnelChartComponent {
  readonly steps = input.required<FunnelStep[]>();

  protected readonly hasData = computed(() => (this.steps() ?? []).length > 0);

  protected readonly series = computed(() => [
    { name: 'Valor', data: (this.steps() ?? []).map((step) => step.total_amount) },
  ]);

  protected readonly categories = computed(() =>
    (this.steps() ?? []).map((step) => step.step_name),
  );

  protected readonly colors = computed(() =>
    (this.steps() ?? []).map((step) => step.step_color || 'var(--color-default-400)'),
  );

  protected readonly chartOptions = {
    plotOptions: {
      bar: { horizontal: true, distributed: true, barHeight: '60%' },
    },
    grid: { show: true, padding: { top: -10, right: 10, bottom: -10 } },
    legend: { show: false },
  };
}

export default SalesFunnelChartComponent;
