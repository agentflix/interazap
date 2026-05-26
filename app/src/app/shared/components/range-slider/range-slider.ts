import { Component, ChangeDetectionStrategy, input } from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';

/**
 * Slider de intervalo com dois controles para seleção de mínimo e máximo.
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
  /** FormControl do valor mínimo */
  readonly minControl = input.required<FormControl<number>>();

  /** FormControl do valor máximo */
  readonly maxControl = input.required<FormControl<number>>();

  /** Valor mínimo absoluto */
  readonly min = input(0);

  /** Valor máximo absoluto */
  readonly max = input(100);

  /** Incremento por passo */
  readonly step = input(1);

  /** Rótulo */
  readonly label = input('');
}
