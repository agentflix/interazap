import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * Indicador de progresso horizontal.
 *
 * @example
 * ```html
 * <af-progress-bar [value]="65" variant="accent" size="md" />
 * ```
 */
@Component({
  selector: 'af-progress-bar',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './progress-bar.html',
})
export class AfProgressBarComponent {
  /** Valor do progresso (0–100) */
  readonly value = input(0);

  /** Rótulo opcional */
  readonly label = input('');

  /** Exibe o rótulo de percentual */
  readonly showLabel = input(true);

  /** Tamanho da barra */
  readonly size = input<'xs' | 'sm' | 'md' | 'lg'>('md');

  /** Variante de cor */
  readonly variant = input<'accent' | 'info' | 'danger' | 'warning'>('accent');

  protected readonly clampedValue = computed(() => Math.max(0, Math.min(100, this.value())));
}
