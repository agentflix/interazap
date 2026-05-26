import { Component, ChangeDetectionStrategy, input } from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';

/**
 * Par de campos de data para seleção de um intervalo (início e fim).
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
  /** FormControl da data de início */
  readonly startControl = input.required<FormControl<string>>();

  /** FormControl da data de fim */
  readonly endControl = input.required<FormControl<string>>();

  /** Rótulo do campo */
  readonly label = input('');

  /** Indica se o campo é obrigatório */
  readonly required = input(false);
}
