import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * Medidor/gauge visual para exibir valores dentro de um intervalo.
 *
 * @example
 * ```html
 * <af-meter [value]="72" [max]="100" label="Uso de CPU" variant="warning" />
 * ```
 */
@Component({
  selector: 'af-meter',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './meter.html',
})
export class AfMeterComponent {
  /** Valor atual */
  readonly value = input(0);

  /** Valor máximo */
  readonly max = input(100);

  /** Rótulo opcional */
  readonly label = input('');

  /** Variante de cor */
  readonly variant = input<'accent' | 'info' | 'warning' | 'danger'>('accent');

  protected readonly percentage = computed(() => {
    const m = this.max();
    return m > 0 ? Math.min(100, (this.value() / m) * 100) : 0;
  });
}
