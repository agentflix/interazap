import { Component, ChangeDetectionStrategy, input } from '@angular/core';

/**
 * Form label atom — renders a styled `<label>` with optional required indicator.
 *
 * @example
 * ```html
 * <af-form-label label="Email" for="email-input" [required]="true" />
 * ```
 */
@Component({
  selector: 'af-form-label',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './form-label.html',
})
export class AfFormLabelComponent {
  /** Label text to display */
  readonly label = input<string>();

  /** HTML for attribute linking to the input id */
  readonly for = input<string>();

  /** Whether to show the red asterisk required indicator */
  readonly required = input(false);
}
