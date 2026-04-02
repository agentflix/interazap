import { ChangeDetectionStrategy, Component, input, computed } from '@angular/core';
import { AfApexchartComponent, AfCardComponent, AfEmptyStateComponent } from '@shared/components';
import { type NegotiationStats } from '../../models/dashboard.model';

/**
 * Negotiation stats — donut chart with status breakdown and loss reasons.
 * Business logic preserved verbatim from source.
 */
@Component({
  selector: 'app-negotiation-stats',
  standalone: true,
  imports: [AfApexchartComponent, AfCardComponent, AfEmptyStateComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { class: 'block' },
  templateUrl: './negotiation-stats.html',
})
export class NegotiationStatsComponent {
  readonly stats = input.required<NegotiationStats>();

  protected readonly chartSeries = computed<number[]>(() => [
    this.stats()?.by_status.won ?? 0,
    this.stats()?.by_status.lost ?? 0,
    this.stats()?.by_status.open ?? 0,
  ]);

  protected readonly hasData = computed(() => this.chartSeries().some((value) => value > 0));

  protected readonly chartOptions = {
    legend: { position: 'bottom' },
    stroke: { show: false },
  };
}

export default NegotiationStatsComponent;
