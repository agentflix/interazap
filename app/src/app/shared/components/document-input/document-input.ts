import {
  Component,
  ChangeDetectionStrategy,
  input,
  signal,
  computed,
  effect,
  inject,
  DestroyRef,
} from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { startWith } from 'rxjs';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { resolveInputContainerClass } from '../input-container.util';

import type { AfInputSize } from './document-input.model';
export * from './document-input.model';

/**
 * AfDocumentInputComponent — Auto-masking CPF/CNPJ input.
 * Detects document type by length and applies appropriate mask.
 *
 * @example
 * ```html
 * <af-document-input [control]="docCtrl" label="CPF/CNPJ" />
 * ```
 */
@Component({
  selector: 'af-document-input, app-document-input',
  standalone: true,
  imports: [ReactiveFormsModule, AfFormLabelComponent, AfFormErrorComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './document-input.html',
})
export class AfDocumentInputComponent {
  private readonly destroyRef = inject(DestroyRef);
  private syncedControl: FormControl<string | null> | null = null;

  /** FormControl for the raw (unmasked) value */
  readonly control = input.required<FormControl<string | null>>();

  /** Container CSS class */
  readonly classContainer = input<string | null>(null);

  /** Enables/disables default vertical spacing */
  readonly spacing = input(true);

  /** Optional helper text displayed below the field */
  readonly helpText = input<string>();

  /** Field label */
  readonly label = input('CPF/CNPJ');

  /** Placeholder */
  readonly placeholder = input('000.000.000-00');

  /** Whether field is required */
  readonly required = input(false);

  /** Error message */
  readonly errorMessage = input('Documento inválido.');

  /** Input size: sm for compact fields, md for the default comfortable field */
  readonly size = input<AfInputSize>('md');

  protected readonly rawValue = signal('');

  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  protected readonly displayValue = computed(() => {
    const raw = this.rawValue();
    if (raw.length <= 11) {
      return this.maskCpf(raw);
    }
    return this.maskCnpj(raw);
  });

  /** Dynamic CSS classes for input based on size */
  protected readonly inputClasses = computed(() => {
    const sizeClasses = this.size() === 'sm' ? 'h-8 px-2.5 text-xs' : 'h-10 px-3 text-sm';
    return [
      'w-full rounded-md border border-neutral-300 dark:border-neutral-600',
      'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-50',
      'placeholder:text-neutral-400 dark:placeholder:text-neutral-500',
      'transition-colors focus:outline-none focus:ring-2 focus:ring-accent-500/30 focus:border-accent-500',
      'disabled:opacity-50 disabled:cursor-not-allowed',
      sizeClasses,
    ].join(' ');
  });

  protected readonly showError = computed(() => this.control().invalid && this.control().touched);

  constructor() {
    effect(() => {
      const control = this.control();
      if (this.syncedControl === control) return;

      this.syncedControl = control;
      control.valueChanges
        .pipe(startWith(control.value), takeUntilDestroyed(this.destroyRef))
        .subscribe((value) => {
          const digits = (value ?? '').replace(/\D/g, '').slice(0, 14);
          if (digits !== this.rawValue()) {
            this.rawValue.set(digits);
          }

          if ((value ?? '') !== digits) {
            control.setValue(digits, { emitEvent: false });
          }
        });
    });
  }

  protected onInput(event: Event): void {
    const input = (event.target as HTMLInputElement).value.replace(/\D/g, '');
    const limited = input.slice(0, 14);
    this.rawValue.set(limited);
    this.control().setValue(limited);

    const el = event.target as HTMLInputElement;
    const formatted = limited.length <= 11 ? this.maskCpf(limited) : this.maskCnpj(limited);
    el.value = formatted;
  }

  protected onBlur(): void {
    this.control().markAsTouched();
  }

  private maskCpf(v: string): string {
    return v
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
  }

  private maskCnpj(v: string): string {
    return v
      .replace(/(\d{2})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d)/, '$1/$2')
      .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
  }
}

export const DocumentInputComponent = AfDocumentInputComponent;
