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
 * Wrapper leve em torno da biblioteca ApexCharts, aplicando os design tokens
 * do InteraZap e reagindo automaticamente às mudanças de tema escuro/claro.
 *
 * Requer `apexcharts` instalado (`npm install apexcharts`). Usa a API
 * JavaScript pura para evitar a dependência do pacote `ngx-apexcharts`.
 *
 * Contexto: utilizado em dashboards e relatórios para exibir gráficos
 * de barras, linhas, área, donuts, pizza e radial.
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
  /** Tipo do gráfico */
  readonly type = input<'line' | 'bar' | 'area' | 'donut' | 'pie' | 'radialBar' | 'radar'>('bar');

  /** Séries de dados */
  readonly series = input.required<ApexAxisChartSeries | ApexNonAxisChartSeries>();

  /** Rótulos de categoria do eixo X */
  readonly categories = input<string[]>([]);

  /** Altura do gráfico em pixels */
  readonly height = input('320');

  /** Título opcional do gráfico */
  readonly chartTitle = input<string>();

  /** Paleta de cores customizada (padrão usa tokens do InteraZap) */
  readonly colors = input<string[]>([
    '#6366f1', // accent-500
    '#8b5cf6', // violet
    '#06b6d4', // cyan
    '#f59e0b', // amber
    '#ef4444', // red
    '#10b981', // emerald
  ]);

  /** Rótulos para gráficos donut/pie/radialBar */
  readonly labels = input<string[]>([]);

  /** Opções extras do ApexCharts mescladas nas configurações padrão */
  readonly extraOptions = input<ApexExtraOptions>({});

  private readonly theme = inject(ThemeService);
  private readonly chartContainer = viewChild<ElementRef<HTMLDivElement>>('chartContainer');

  /** Interface mínima para instância do ApexCharts */
  private chart: { destroy(): void; render(): Promise<unknown> } | null = null;
  private renderVersion = 0;

  constructor() {
    // Renderiza o gráfico quando inputs ou tema mudam
    effect(() => {
      // Lê inputs reativos para rastreá-los
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
          fontFamily: 'Figtree, system-ui, sans-serif',
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
