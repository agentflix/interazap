import { Component, ChangeDetectionStrategy, input } from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';

/**
 * AfDateRangePickerComponent — Paired date inputs for selecting a range.
 *
 * @example
 * ```html
 * <af-date-range-picker
 *   [startControl]="startCtrl"
 *   [endControl]="endCtrl"
 *   label="Período"
 * />
 * ```
 */
@Component({
  selector: 'af-date-range-picker',
  standalone: true,
  imports: [ReactiveFormsModule, AfFormLabelComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './date-range-picker.html',
})
export class AfDateRangePickerComponent {
  /** Start date FormControl */
  readonly startControl = input.required<FormControl<string>>();

  /** End date FormControl */
  readonly endControl = input.required<FormControl<string>>();

  /** Field label */
  readonly label = input('');

  /** Required */
  readonly required = input(false);
}
