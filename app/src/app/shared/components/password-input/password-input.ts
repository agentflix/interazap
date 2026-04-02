import { Component, ChangeDetectionStrategy, input, computed, signal, output } from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { LucideAngularModule } from 'lucide-angular';
import { resolveInputContainerClass } from '../input-container.util';

import type { AfInputSize } from './password-input.model';
export * from './password-input.model';



/**
 * Password input with show/hide toggle.
 *
 * @description Input field that toggles between text and password visibility
 * via an eye icon button. Ideal for login, register, and settings forms.
 *
 * @example
 * ```html
 * <af-password-input
 *   [control]="form.controls.password"
 *   label="Senha"
 *   [required]="true"
 *   errorMessage="Senha é obrigatória."
 * />
 * ```
 */
@Component({
  selector: 'af-password-input',
  standalone: true,
  imports: [ReactiveFormsModule, AfFormLabelComponent, AfFormErrorComponent, LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './password-input.html',
})
export class AfPasswordInputComponent {
  /** FormControl for the password */
  readonly control = input.required<FormControl<string>>();

  /** Label text */
  readonly label = input<string>();

  /** Container CSS class */
  readonly classContainer = input<string | null>(null);

  /** Enables/disables default vertical spacing */
  readonly spacing = input(true);

  /** Optional helper text displayed below the field */
  readonly helpText = input<string>();

  /** Placeholder text */
  readonly placeholder = input('••••••••');

  /** Required asterisk on label */
  readonly required = input(false);

  /** Error message */
  readonly errorMessage = input('Senha é obrigatória.');

  /** data-test attribute */
  readonly dataTest = input<string>();

  /** Input size: sm or md */
  readonly size = input<AfInputSize>('sm');

  /** Whether the input is read-only (prevents browser autofill) */
  readonly disableAutofill = input(false);

  /** Emitted when the input receives focus */
  readonly focusEmitter = output<void>();

  /** Emitted when the input loses focus */
  readonly blurEmitter = output<void>();

  /** Autocomplete attribute for browser autofill control */
  readonly autocomplete = input<string>('off');

  /** Password visibility state */
  protected readonly visible = signal(false);

  /** Unique ID */
  protected readonly inputId = `password-${Math.random().toString(36).slice(2, 9)}`;

  /** Dynamic CSS classes */
  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  /** Dynamic CSS classes */
  protected readonly inputClasses = computed(() => {
    const borderColor = this.showError()
      ? 'border-red-500 dark:border-red-400'
      : 'border-neutral-300 dark:border-neutral-600';

    const sizeClasses =
      this.size() === 'sm' ? 'h-8 pl-2.5 pr-9 text-xs' : 'h-10 pl-3 pr-10 text-sm';

    return [
      'w-full rounded-md border',
      sizeClasses,
      'bg-white dark:bg-neutral-900',
      'text-neutral-900 dark:text-neutral-50',
      'placeholder:text-neutral-400 dark:placeholder:text-neutral-500',
      'transition-colors duration-150',
      'focus:outline-none focus:ring-2 focus:ring-accent-500/30 focus:border-accent-500',
      borderColor,
    ].join(' ');
  });

  /** Whether to show error */
  protected readonly showError = computed(() => this.control()?.invalid && this.control()?.touched);

  /** Toggle password visibility */
  protected toggleVisibility(): void {
    this.visible.update((v) => !v);
  }
}
