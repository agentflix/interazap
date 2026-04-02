import { Component, ChangeDetectionStrategy, input } from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';

/**
 * AfTimePickerComponent — Native time input with design system styling.
 *
 * @example
 * ```html
 * <af-time-picker [control]="timeCtrl" label="Horário" />
 * ```
 */
@Component({
  selector: 'af-time-picker',
  standalone: true,
  imports: [ReactiveFormsModule, AfFormLabelComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './time-picker.html',
})
export class AfTimePickerComponent {
  /** FormControl binding */
  readonly control = input.required<FormControl<string>>();

  /** Field label */
  readonly label = input('');

  /** Required */
  readonly required = input(false);
}
