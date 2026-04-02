import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';
import { AfSkeletonComponent } from '../skeleton/skeleton';
import { LucideAngularModule } from 'lucide-angular';

/**
 * AfKpiCardComponent — Displays a single KPI metric with an optional
 * change indicator (positive/negative trend).
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
  /** Metric label */
  readonly title = input.required<string>();

  /** Formatted value to display */
  readonly value = input.required<string>();

  /** Percentage change (+ or -). Pass null to hide. */
  readonly change = input<number | null>(null);

  /** Description for the change (e.g., "vs mês anterior") */
  readonly changeLabel = input<string>();

  /** Lucide icon name */
  readonly icon = input<string>();

  /** Whether the card is in loading state */
  readonly loading = input(false);

  /** Whether the change is positive */
  protected readonly isPositive = computed(() => (this.change() ?? 0) >= 0);

  /** Arrow icon based on trend direction */
  protected readonly changeIcon = computed(() =>
    this.isPositive() ? 'trending-up' : 'trending-down',
  );

  /** CSS classes for the change indicator */
  protected readonly changeClasses = computed(() => {
    const base = 'inline-flex items-center gap-0.5 text-xs font-semibold';
    return this.isPositive()
      ? `${base} text-accent-600 dark:text-accent-400`
      : `${base} text-red-600 dark:text-red-400`;
  });

  /** Absolute formatted change value */
  protected readonly formattedChange = computed(() => {
    const val = this.change() ?? 0;
    return Math.abs(val).toFixed(1);
  });
}
