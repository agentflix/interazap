import { Component, ChangeDetectionStrategy, input } from '@angular/core';

/**
 * Card compacto de estatística/número para seções de visão geral.
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
  /** Rótulo da estatística */
  readonly label = input('');

  /** Valor de exibição */
  readonly value = input('');

  /** Texto opcional de variação */
  readonly change = input('');

  /** Sentimento da variação */
  readonly changeType = input<'positive' | 'negative' | 'neutral'>('neutral');
}
