import { Component, ChangeDetectionStrategy, input, computed, output, signal, effect } from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { resolveInputContainerClass } from '../input-container.util';

import type { AfTextInputType, AfInputSize } from './text-input.model';
export * from './text-input.model';

/**
 * Text input component for InteraZap UI Kit.
 *
 * @description Standardized text input with label, validation error, and E2E test support.
 * Works with Angular Reactive Forms via FormControl passed as input.
 *
 * @example
 * ```html
 * <af-text-input
 *   [control]="form.controls.name"
 *   label="Nome"
 *   placeholder="Ex: João Silva"
 *   [required]="true"
 *   errorMessage="Nome é obrigatório."
 *   dataTest="contact-form-name"
 * />
 * ```
 */
@Component({
  selector: 'af-text-input, app-text-input',
  standalone: true,
  imports: [ReactiveFormsModule, AfFormLabelComponent, AfFormErrorComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './text-input.html',
})
export class AfTextInputComponent {
  /** FormControl for the input (accepts string or number) */
  readonly control = input.required<FormControl<string | number | null>>();

  /** Container CSS class */
  readonly classContainer = input<string | null>(null);

  /** Enables/disables default vertical spacing */
  readonly spacing = input(true);

  /** Optional helper text displayed below the input */
  readonly helpText = input<string>();

  /** Extra CSS class for the input element */
  readonly classInput = input<string>('');

  /** Label text */
  readonly label = input<string>();

  /** Placeholder text */
  readonly placeholder = input('');

  /** Input type: text, email, password, url, number, date, time, datetime-local */
  readonly type = input<AfTextInputType>('text');

  /** Whether the input is read-only */
  readonly readOnly = input(false);

  /** Prevents browser autofill — use this for security on focus/blur */
  readonly disableAutofill = input(false);

  /** Autocomplete attribute for browser autofill control */
  readonly autocomplete = input<string>('off');

  /** Maximum character length */
  readonly maxLength = input<number | null>(null);

  /** Maximum value for number inputs */
  readonly max = input<number | string | null>(null);

  /** data-test attribute for E2E tests */
  readonly dataTest = input<string>();

  /** Whether to show the required asterisk on the label */
  readonly required = input(false);

  /** Error message to display when invalid */
  readonly errorMessage = input('Campo obrigatório.');

  /** Input size: sm for compact fields, md for the default comfortable field */
  readonly size = input<AfInputSize>('md');

  /** Accessible label for screen readers */
  readonly ariaLabel = input<string>();

  /** Accessible role for the input */
  readonly role = input<string>();

  /** Emitted when the input receives focus */
  readonly focusEmitter = output<void>();

  /** Emitted when the input loses focus */
  readonly blurEmitter = output<void>();

  /** Unique ID for label-input association */
  protected readonly inputId = `input-${Math.random().toString(36).slice(2, 9)}`;

  private readonly controlRevision = signal(0);

  constructor() {
    effect((onCleanup) => {
      const subscription = this.control().events.subscribe(() => {
        this.controlRevision.update((n) => n + 1);
      });
      onCleanup(() => subscription.unsubscribe());
    });
  }

  /** Dynamic CSS classes based on control state and size */
  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  /** Dynamic CSS classes based on control state and size */
  protected readonly inputClasses = computed(() => {
    const sizeClasses = this.size() === 'sm' ? 'h-8 px-2.5 text-xs' : 'h-10 px-3 text-sm';

    const base = [
      'w-full rounded-md border bg-white dark:bg-neutral-900',
      'text-neutral-900 dark:text-neutral-50',
      'placeholder:text-neutral-400 dark:placeholder:text-neutral-500',
      'transition-colors duration-150',
      'focus:outline-none focus:ring-2 focus:ring-accent-500/30 focus:border-accent-500',
      'disabled:opacity-50 disabled:cursor-not-allowed',
      'read-only:bg-neutral-50 dark:read-only:bg-neutral-800 read-only:cursor-default',
      sizeClasses,
    ];

    const borderColor = this.showError()
      ? 'border-red-500 dark:border-red-400'
      : 'border-neutral-300 dark:border-neutral-600';

    const extra = this.classInput();

    return [...base, borderColor, extra].filter(Boolean).join(' ');
  });

  /** Whether to show the error message */
  protected readonly showError = computed(() => {
    this.controlRevision();
    return !!this.control()?.invalid && !!this.control()?.touched;
  });

  /** Error message: server error takes precedence over static errorMessage input */
  protected readonly resolvedErrorMessage = computed(() => {
    this.controlRevision();
    const serverMsg = this.control()?.errors?.['server'];
    return typeof serverMsg === 'string' ? serverMsg : this.errorMessage();
  });
}

export const TextInputComponent = AfTextInputComponent;
