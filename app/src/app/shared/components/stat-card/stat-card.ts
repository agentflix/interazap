import { Component, ChangeDetectionStrategy, input } from '@angular/core';

/**
 * AfStatCardComponent — Compact stat/number card for overview sections.
 *
 * @example
 * ```html
 * <af-stat-card label="Total Vendas" value="R$ 125.430" change="+12.5%" changeType="positive" />
 * ```
 */
@Component({
  selector: 'af-stat-card',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './stat-card.html',
})
export class AfStatCardComponent {
  /** Stat label */
  readonly label = input('');

  /** Display value */
  readonly value = input('');

  /** Optional change indicator text */
  readonly change = input('');

  /** Change sentiment */
  readonly changeType = input<'positive' | 'negative' | 'neutral'>('neutral');
}
