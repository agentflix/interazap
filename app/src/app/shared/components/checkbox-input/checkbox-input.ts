import {
  Component,
  ChangeDetectionStrategy,
  DestroyRef,
  computed,
  effect,
  inject,
  input,
  signal,
} from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { startWith } from 'rxjs';
import { AfFormErrorComponent } from '../form-error/form-error';
import { resolveInputContainerClass } from '../input-container.util';

/**
 * Checkbox input component for InteraZap UI Kit.
 *
 * @description Styled checkbox with inline label and FormControl integration.
 * Uses a custom visual checkbox (hidden native input + styled div overlay) to
 * ensure the checked state is always visible, including when set programmatically
 * (e.g. selectAll / bulk operations).
 *
 * @example
 * ```html
 * <af-checkbox-input
 *   [control]="form.controls.acceptTerms"
 *   label="Li e aceito os termos"
 *   dataTest="login-accept-terms"
 * />
 * ```
 */
@Component({
  selector: 'af-checkbox-input, app-checkbox-input',
  standalone: true,
  imports: [ReactiveFormsModule, AfFormErrorComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './checkbox-input.html',
})
export class AfCheckboxInputComponent {
  private readonly destroyRef = inject(DestroyRef);
  private syncedControl: FormControl<boolean> | null = null;

  /** FormControl for the checkbox */
  readonly control = input.required<FormControl<boolean>>();

  /** Inline label text */
  readonly label = input<string>('');

  /** data-test attribute for E2E tests */
  readonly dataTest = input<string>();

  /** aria-label for accessibility when no visible label is present */
  readonly ariaLabel = input<string>();

  /** Container CSS class */
  readonly classContainer = input<string | null>(null);

  /** Enables/disables default vertical spacing */
  readonly spacing = input(true);

  /** Optional helper text displayed below the field */
  readonly helpText = input<string>();

  /** Optional error message when control is invalid */
  readonly errorMessage = input('Campo obrigatório.');

  /** Unique ID for label-input association */
  protected readonly checkboxId = `checkbox-${Math.random().toString(36).slice(2, 9)}`;

  /** Reactive checked state — updated via valueChanges subscription */
  protected readonly isChecked = signal(false);

  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  protected readonly showError = computed(() => this.control().invalid && this.control().touched);

  /** Dynamic box classes based on checked state */
  protected readonly boxClasses = () => {
    const base = [
      'mt-0.5 size-4 shrink-0 rounded border cursor-pointer',
      'flex items-center justify-center',
      'transition-colors duration-150',
      'focus:outline-none focus:ring-2 focus:ring-accent-500/30 focus:ring-offset-0',
    ];
    const state = this.isChecked()
      ? 'bg-accent-500 border-accent-500'
      : 'bg-white dark:bg-neutral-900 border-neutral-300 dark:border-neutral-600';
    return [...base, state].join(' ');
  };

  constructor() {
    // Subscribe to control value changes (including programmatic setValue) to keep
    // the isChecked signal in sync — fixes bulk-select / selectAll with OnPush.
    effect(() => {
      const ctrl = this.control();
      if (ctrl === this.syncedControl) return;
      this.syncedControl = ctrl;

      ctrl.valueChanges
        .pipe(startWith(ctrl.value), takeUntilDestroyed(this.destroyRef))
        .subscribe((val) => this.isChecked.set(!!val));
    });
  }

  /** Toggle the control value when the custom box or label is clicked */
  protected toggle(): void {
    if (this.control().disabled) return;
    this.control().setValue(!this.control().value);
    this.control().markAsTouched();
  }

  /** Keep native checkbox in sync when the user interacts with it directly */
  protected onNativeChange(event: Event): void {
    const checked = (event.target as HTMLInputElement).checked;
    this.isChecked.set(checked);
  }
}

export const CheckboxInputComponent = AfCheckboxInputComponent;
