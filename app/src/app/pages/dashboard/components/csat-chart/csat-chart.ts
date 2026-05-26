import { ChangeDetectionStrategy, Component, input, computed } from '@angular/core';
import { AfApexchartComponent, AfCardComponent, AfEmptyStateComponent } from '@shared/components';
import { type CsatStats } from '../../models/dashboard.model';

interface RatingRow {
  rating: number;
  count: number;
  percent: number;
}

/**
 * Gráfico CSAT — barra radial com distribuição de notas de satisfação.
 */
@Component({
  selector: 'app-csat-chart',
  standalone: true,
  imports: [AfApexchartComponent, AfCardComponent, AfEmptyStateComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { class: 'block' },
  templateUrl: './csat-chart.html',
})
export class CsatChartComponent {
  readonly stats = input.required<CsatStats>();

  protected readonly hasData = computed(() => (this.stats()?.total_evaluations ?? 0) > 0);

  protected readonly ratingRows = computed<RatingRow[]>(() => {
    const total = this.stats()?.total_evaluations ?? 0;
    return [5, 4, 3, 2, 1].map((rating) => {
      const count = this.stats()?.distribution?.[rating as 1 | 2 | 3 | 4 | 5] ?? 0;
      const rowPercent = total > 0 ? Math.round((count / total) * 100) : 0;
      return { rating, count, percent: rowPercent };
    });
  });

  protected readonly chartSeries = computed<number[]>(() => {
    const average = this.stats()?.average_rating ?? 0;
    const percent = average > 0 ? (average / 5) * 100 : 0;
    return [Number(percent.toFixed(2))];
  });

  protected readonly chartOptions = computed(() => {
    const average = this.stats()?.average_rating ?? 0;
    return {
      chart: { offsetY: -10, sparkline: { enabled: true } },
      plotOptions: {
        radialBar: {
          startAngle: -90,
          endAngle: 90,
          track: { strokeWidth: '97%', margin: 5 },
          dataLabels: {
            name: { show: false },
            value: {
              offsetY: -5,
              fontSize: '22px',
              fontWeight: '600',
              formatter: (): string => average.toFixed(1),
            },
          },
        },
      },
      grid: { padding: { top: -10 } },
      fill: {
        type: 'gradient',
        gradient: {
          shade: 'light',
          shadeIntensity: 0.4,
          inverseColors: false,
          opacityFrom: 1,
          opacityTo: 1,
          stops: [0, 50, 53, 91],
        },
      },
      stroke: { dashArray: 4 },
      labels: ['CSAT'],
    };
  });
}

export default CsatChartComponent;
