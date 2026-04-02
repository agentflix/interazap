import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { resolveInputContainerClass } from '../input-container.util';

import type { AfRadioOption } from './radio-input.model';
export * from './radio-input.model';



/**
 * AfRadioInputComponent — Styled radio button group with horizontal/vertical layout.
 *
 * @example
 * ```html
 * <af-radio-input
 *   [control]="statusControl"
 *   [options]="statusOptions"
 *   name="status"
 *   label="Status"
 *   orientation="horizontal"
 * />
 * ```
 */
@Component({
  selector: 'af-radio-input',
  standalone: true,
  imports: [ReactiveFormsModule, AfFormLabelComponent, AfFormErrorComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './radio-input.html',
})
export class AfRadioInputComponent {
  /** FormControl for the radio group */
  readonly control = input.required<FormControl<string>>();

  /** Available options */
  readonly options = input.required<AfRadioOption[]>();

  /** Radio group name (HTML name attribute) */
  readonly name = input.required<string>();

  /** Group label */
  readonly label = input<string>();

  /** Show required asterisk */
  readonly required = input(false);

  /** Layout orientation */
  readonly orientation = input<'horizontal' | 'vertical'>('vertical');

  /** Container CSS class */
  readonly classContainer = input<string | null>(null);

  /** Enables/disables default vertical spacing */
  readonly spacing = input(true);

  /** Optional helper text displayed below the field */
  readonly helpText = input<string>();

  /** Error message */
  readonly errorMessage = input('Selecione uma opção.');

  /** data-test attribute for E2E */
  readonly dataTest = input<string>();

  /** Group flex direction based on orientation */
  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  /** Group flex direction based on orientation */
  protected readonly groupClasses = computed(() => {
    const base = 'flex gap-3';
    return this.orientation() === 'horizontal' ? `${base} flex-row flex-wrap` : `${base} flex-col`;
  });

  /** Classes for each option label */
  protected optionClasses(option: AfRadioOption): string {
    const base = 'inline-flex items-center gap-2.5 select-none';
    const cursor = option.disabled ? 'cursor-not-allowed' : 'cursor-pointer';
    return `${base} ${cursor}`;
  }

  /** Whether to show error */
  protected readonly showError = computed(() => this.control()?.invalid && this.control()?.touched);
}
