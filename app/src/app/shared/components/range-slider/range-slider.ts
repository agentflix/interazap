import { Component, ChangeDetectionStrategy, input } from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';

/**
 * AfRangeSliderComponent — Dual-handle range slider for min/max selection.
 *
 * @example
 * ```html
 * <af-range-slider [minControl]="minCtrl" [maxControl]="maxCtrl" [min]="0" [max]="1000" label="Preço" />
 * ```
 */
@Component({
  selector: 'af-range-slider',
  standalone: true,
  imports: [ReactiveFormsModule, AfFormLabelComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './range-slider.html',
})
export class AfRangeSliderComponent {
  /** Min value control */
  readonly minControl = input.required<FormControl<number>>();

  /** Max value control */
  readonly maxControl = input.required<FormControl<number>>();

  /** Minimum */
  readonly min = input(0);

  /** Maximum */
  readonly max = input(100);

  /** Step */
  readonly step = input(1);

  /** Label */
  readonly label = input('');
}
