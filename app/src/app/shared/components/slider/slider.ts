import { Component, ChangeDetectionStrategy, input, output, computed } from '@angular/core';

/**
 * AfSliderComponent — Range slider input.
 *
 * @example
 * ```html
 * <af-slider [value]="50" [min]="0" [max]="100" (valueChange)="onSlide($event)" />
 * ```
 */
@Component({
  selector: 'af-slider',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './slider.html',
})
export class AfSliderComponent {
  /** Current value */
  readonly value = input(50);

  /** Minimum */
  readonly min = input(0);

  /** Maximum */
  readonly max = input(100);

  /** Step */
  readonly step = input(1);

  /** Label */
  readonly label = input('');

  /** Show current value */
  readonly showValue = input(true);

  /** Disabled */
  readonly disabled = input(false);

  /** Emitted on value change */
  readonly valueChange = output<number>();

  protected onInput(event: Event): void {
    const val = +(event.target as HTMLInputElement).value;
    this.valueChange.emit(val);
  }
}
