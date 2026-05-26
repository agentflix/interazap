import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';
import { AfSkeletonComponent } from '../skeleton/skeleton';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Exibe uma única métrica KPI com indicador opcional de variação (tendência positiva/negativa).
 *
 * @example
 * ```html
 * <af-kpi-card
 *   title="Receita"
 *   value="R$ 48.250"
 *   [change]="12.5"
 *   changeLabel="vs mês anterior"
 *   icon="dollar-sign"
 * />
 * ```
 */
@Component({
  selector: 'af-kpi-card',
  standalone: true,
  imports: [AfSkeletonComponent, LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './kpi-card.html',
})
export class AfKpiCardComponent {
  /** Rótulo da métrica */
  readonly title = input.required<string>();

  /** Valor formatado a exibir */
  readonly value = input.required<string>();

  /** Variação percentual (+ ou -). Passar null para ocultar. */
  readonly change = input<number | null>(null);

  /** Descrição da variação (ex.: "vs mês anterior") */
  readonly changeLabel = input<string>();

  /** Nome do ícone Lucide */
  readonly icon = input<string>();

  /** Indica se o card está em estado de carregamento */
  readonly loading = input(false);

  /** Indica se a variação é positiva */
  protected readonly isPositive = computed(() => (this.change() ?? 0) >= 0);

  /** Ícone de seta baseado na direção da tendência */
  protected readonly changeIcon = computed(() =>
    this.isPositive() ? 'trending-up' : 'trending-down',
  );

  /** Classes CSS do indicador de variação */
  protected readonly changeClasses = computed(() => {
    const base = 'inline-flex items-center gap-0.5 text-xs font-semibold';
    return this.isPositive()
      ? `${base} text-accent-600 dark:text-accent-400`
      : `${base} text-red-600 dark:text-red-400`;
  });

  /** Valor absoluto formatado da variação */
  protected readonly formattedChange = computed(() => {
    const val = this.change() ?? 0;
    return Math.abs(val).toFixed(1);
  });
}
