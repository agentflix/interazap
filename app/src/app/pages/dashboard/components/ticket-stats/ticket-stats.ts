import { ChangeDetectionStrategy, Component, input, computed } from '@angular/core';
import { DecimalPipe } from '@angular/common';
import { AfApexchartComponent, AfCardComponent, AfEmptyStateComponent } from '@shared/components';
import { type TicketStats } from '../../models/dashboard.model';

/**
 * Estatísticas de tickets — gráfico de linhas com volume diário e distribuição por prioridade.
 */
@Component({
  selector: 'app-ticket-stats',
  standalone: true,
  imports: [AfApexchartComponent, AfCardComponent, AfEmptyStateComponent, DecimalPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { class: 'block' },
  templateUrl: './ticket-stats.html',
})
export class TicketStatsComponent {
  readonly stats = input.required<TicketStats>();

  protected readonly hasData = computed(() => (this.stats()?.daily_volume ?? []).length > 0);

  protected readonly series = computed(() => [
    { name: 'Tickets', data: (this.stats()?.daily_volume ?? []).map((item) => item.count) },
  ]);

  protected readonly categories = computed(() =>
    (this.stats()?.daily_volume ?? []).map((item) => item.date),
  );

  protected readonly chartOptions = {
    stroke: { curve: 'smooth', width: 2 },
    grid: { show: true, padding: { top: -20, right: 0 } },
  };
}

export default TicketStatsComponent;
