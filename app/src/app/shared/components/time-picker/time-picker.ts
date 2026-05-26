import { Component, ChangeDetectionStrategy, input } from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';

/**
 * Campo de hora nativo com estilização do design system.
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
  /** Vinculação ao FormControl */
  readonly control = input.required<FormControl<string>>();

  /** Rótulo do campo */
  readonly label = input('');

  /** Obrigatório */
  readonly required = input(false);
}
