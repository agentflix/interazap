import { Component, ChangeDetectionStrategy, input } from '@angular/core';

/**
 * Form error message atom — renders a validation error below an input.
 *
 * @example
 * ```html
 * <af-form-error [show]="showError()" message="This field is required." />
 * ```
 */
@Component({
  selector: 'af-form-error',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './form-error.html',
})
export class AfFormErrorComponent {
  /** Whether to display the error message */
  readonly show = input(false);

  /** Error message text */
  readonly message = input('Campo obrigatório.');
}
