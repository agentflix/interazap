import {
  type ElementRef,
  type OnDestroy,
  Component,
  ChangeDetectionStrategy,
  input,
  effect,
  inject,
  viewChild,
} from '@angular/core';
import { ThemeService } from '../../../core/services/theme.service';

/** ApexCharts series types (local aliases to avoid sparse third-party type imports) */
type ApexAxisChartSeries = { name?: string; data: number[] }[];
type ApexNonAxisChartSeries = number[];
type ApexChartSeries = ApexAxisChartSeries | ApexNonAxisChartSeries;

/** Minimal ApexCharts options type for extraOptions input */
type ApexExtraOptions = Record<string, unknown>;

/**
 * AfApexchartComponent — Lightweight wrapper around the ApexCharts library
 * that applies AgentFlix design tokens and reacts to dark mode changes.
 *
 * > Note: Requires `apexcharts` to be installed (`npm install apexcharts`).
 * > This component uses the vanilla JS API directly to avoid the need for
 * > the `ngx-apexcharts` Angular wrapper dependency.
 *
 * @example
 * ```html
 * <af-apexchart
 *   type="bar"
 *   [series]="[{ name: 'Receita', data: [44, 55, 41, 67, 22] }]"
 *   [categories]="['Jan', 'Fev', 'Mar', 'Abr', 'Mai']"
 *   height="320"
 * />
 * ```
 */
@Component({
  selector: 'af-apexchart',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './apexchart.html',
})
export class AfApexchartComponent implements OnDestroy {
  /** Chart type */
  readonly type = input<'line' | 'bar' | 'area' | 'donut' | 'pie' | 'radialBar' | 'radar'>('bar');

  /** Data series */
  readonly series = input.required<ApexAxisChartSeries | ApexNonAxisChartSeries>();

  /** X-axis category labels */
  readonly categories = input<string[]>([]);

  /** Chart height in pixels */
  readonly height = input('320');

  /** Optional chart title */
  readonly chartTitle = input<string>();

  /** Custom color palette (defaults to AgentFlix tokens) */
  readonly colors = input<string[]>([
    '#6366f1', // accent-500
    '#8b5cf6', // violet
    '#06b6d4', // cyan
    '#f59e0b', // amber
    '#ef4444', // red
    '#10b981', // emerald
  ]);

  /** Labels for donut/pie/radialBar charts */
  readonly labels = input<string[]>([]);

  /** Extra ApexCharts options deep-merged into defaults (stacked, horizontal, radialBar, etc.) */
  readonly extraOptions = input<ApexExtraOptions>({});

  private readonly theme = inject(ThemeService);
  private readonly chartContainer = viewChild<ElementRef<HTMLDivElement>>('chartContainer');

  /** Minimal interface for ApexCharts instance (avoids importing sparse third-party types) */
  private chart: { destroy(): void; render(): Promise<unknown> } | null = null;
  private renderVersion = 0;

  constructor() {
    // Render chart when inputs or theme changes
    effect(() => {
      // Read reactive inputs to track them
      const type = this.type();
      const series = this.series();
      const categories = this.categories();
      const height = this.height();
      const colors = this.colors();
      const labels = this.labels();
      const extraOptions = this.extraOptions();
      const isDark = this.theme.isDark();
      const container = this.chartContainer();

      if (!container) return;

      void this.renderChart(container.nativeElement, {
        type,
        series,
        categories,
        height: parseInt(height, 10),
        colors,
        labels,
        extraOptions,
        isDark,
      });
    });
  }

  private async renderChart(
    container: HTMLDivElement,
    opts: {
      type: string;
      series: ApexChartSeries;
      categories: string[];
      height: number;
      colors: string[];
      labels: string[];
      extraOptions: ApexExtraOptions;
      isDark: boolean;
    },
  ): Promise<void> {
    const currentRenderVersion = ++this.renderVersion;

    if (!container.isConnected) {
      return;
    }

    try {
      // Standard dynamic import (CSP-safe)

      const mod = await import('apexcharts');
      const ApexCharts = mod.default;

      if (currentRenderVersion !== this.renderVersion || !container.isConnected) {
        return;
      }

      if (this.chart) {
        this.chart.destroy();
      }

      const textColor = opts.isDark ? '#a3a3a3' : '#737373';
      const gridColor = opts.isDark ? '#262626' : '#f5f5f5';

      const options = {
        chart: {
          type: opts.type,
          height: opts.height,
          toolbar: { show: false },
          background: 'transparent',
          fontFamily: 'Inter, system-ui, sans-serif',
        },
        series: opts.series,
        xaxis: {
          categories: opts.categories,
          labels: { style: { colors: textColor, fontSize: '12px' } },
          axisBorder: { color: gridColor },
          axisTicks: { color: gridColor },
        },
        yaxis: {
          labels: { style: { colors: textColor, fontSize: '12px' } },
        },
        colors: opts.colors,
        grid: {
          borderColor: gridColor,
          strokeDashArray: 4,
        },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth' as const, width: 2 },
        fill: {
          type: opts.type === 'area' ? 'gradient' : 'solid',
          gradient: { opacityFrom: 0.4, opacityTo: 0.05 },
        },
        tooltip: {
          theme: opts.isDark ? 'dark' : 'light',
        },
        legend: {
          labels: { colors: textColor },
          fontSize: '12px',
        },
        plotOptions: {
          bar: { borderRadius: 4, columnWidth: '60%' },
          pie: { donut: { size: '65%' } },
        },
        theme: { mode: opts.isDark ? ('dark' as const) : ('light' as const) },
      };

      const merged = this.deepMerge(options, opts.extraOptions);
      if (opts.labels.length > 0) {
        merged['labels'] = opts.labels;
      }

      const chart = new ApexCharts(container, merged);
      this.chart = chart;
      chart.render().catch(() => {
        // ApexCharts render errors are already handled by try-catch fallback
      });
    } catch {
      if (!container.isConnected) {
        return;
      }

      // ApexCharts not installed — show fallback
      container.innerHTML = `
        <div class="flex items-center justify-center h-[${opts.height}px] text-sm text-neutral-400 dark:text-neutral-500">
          <p>Instale <code class="text-accent-500">apexcharts</code> para visualizar gráficos.</p>
        </div>
      `;
    }
  }

  private deepMerge(target: ApexExtraOptions, source: ApexExtraOptions): ApexExtraOptions {
    const result: ApexExtraOptions = { ...target };
    for (const key of Object.keys(source)) {
      const sourceValue = source[key];
      const targetValue = target[key];
      if (
        sourceValue !== null &&
        typeof sourceValue === 'object' &&
        !Array.isArray(sourceValue) &&
        targetValue !== null &&
        typeof targetValue === 'object' &&
        !Array.isArray(targetValue)
      ) {
        result[key] = this.deepMerge(
          targetValue as ApexExtraOptions,
          sourceValue as ApexExtraOptions,
        );
      } else {
        result[key] = sourceValue;
      }
    }
    return result;
  }

  ngOnDestroy(): void {
    this.renderVersion++;
    if (this.chart) {
      this.chart.destroy();
      this.chart = null;
    }
  }
}
