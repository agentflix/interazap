import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';

/**
 * AfMeterComponent — Visual meter/gauge for displaying values within a range.
 *
 * @example
 * ```html
 * <af-meter [value]="72" [max]="100" label="CPU Usage" variant="warning" />
 * ```
 */
@Component({
  selector: 'af-meter',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './meter.html',
})
export class AfMeterComponent {
  /** Current value */
  readonly value = input(0);

  /** Maximum value */
  readonly max = input(100);

  /** Optional label */
  readonly label = input('');

  /** Color variant */
  readonly variant = input<'accent' | 'info' | 'warning' | 'danger'>('accent');

  protected readonly percentage = computed(() => {
    const m = this.max();
    return m > 0 ? Math.min(100, (this.value() / m) * 100) : 0;
  });
}
