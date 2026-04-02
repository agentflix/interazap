import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { AfMaskDirective, type AfMaskPreset } from '../../directives/mask.directive';
import { resolveInputContainerClass } from '../input-container.util';

/**
 * Masked input component for InteraZap UI Kit.
 *
 * @description Input with auto-formatting masks for Brazilian formats:
 * CPF, CNPJ, CPF/CNPJ auto-detect, CEP, phone, and currency (R$).
 *
 * @example
 * ```html
 * <af-masked-input [control]="cpfControl" label="CPF" mask="cpf" />
 * <af-masked-input [control]="priceControl" label="Valor" mask="currency" />
 * <af-masked-input [control]="docControl" label="CPF/CNPJ" mask="cpf-cnpj" />
 * ```
 */
@Component({
  selector: 'af-masked-input, app-masked-input',
  standalone: true,
  imports: [ReactiveFormsModule, AfFormLabelComponent, AfFormErrorComponent, AfMaskDirective],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './masked-input.html',
})
export class AfMaskedInputComponent {
  /** FormControl for the input */
  readonly control = input.required<FormControl<string>>();

  /** Mask preset to apply */
  readonly mask = input.required<AfMaskPreset | string>();

  /** Label text */
  readonly label = input<string>();

  /** Container CSS class */
  readonly classContainer = input<string | null>(null);

  /** Enables/disables default vertical spacing */
  readonly spacing = input(true);

  /** Optional helper text displayed below the field */
  readonly helpText = input<string>();

  /** Required asterisk on label */
  readonly required = input(false);

  /** Custom error message */
  readonly errorMessage = input('Campo inválido.');

  /** Custom placeholder (overrides auto) */
  readonly placeholder = input<string>();

  /** data-test attribute */
  readonly dataTest = input<string>();

  /** Input size */
  readonly size = input<'sm' | 'md'>('sm');

  /** Unique ID */
  protected readonly inputId = `masked-${Math.random().toString(36).slice(2, 9)}`;

  /** Auto-generated placeholder based on mask preset */
  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  /** Auto-generated placeholder based on mask preset */
  protected readonly placeholderText = computed(() => {
    if (this.placeholder()) return this.placeholder()!;

    const presets: Record<string, string> = {
      cpf: '000.000.000-00',
      cnpj: '00.000.000/0000-00',
      cep: '00000-000',
      phone: '(00) 00000-0000',
      currency: 'R$ 0,00',
      'cpf-cnpj': 'CPF ou CNPJ',
    };

    return presets[this.mask()] ?? '';
  });

  /** Dynamic CSS classes */
  protected readonly inputClasses = computed(() => {
    const sizeClasses = this.size() === 'sm' ? 'h-8 px-2.5 text-xs' : 'h-10 px-3 text-sm';

    const borderColor = this.showError()
      ? 'border-red-500 dark:border-red-400'
      : 'border-neutral-300 dark:border-neutral-600';

    return [
      'w-full rounded-md border bg-white dark:bg-neutral-900',
      'text-neutral-900 dark:text-neutral-50',
      'placeholder:text-neutral-400 dark:placeholder:text-neutral-500',
      'transition-colors duration-150',
      'focus:outline-none focus:ring-2 focus:ring-accent-500/30 focus:border-accent-500',
      'disabled:opacity-50 disabled:cursor-not-allowed',
      sizeClasses,
      borderColor,
    ].join(' ');
  });

  /** Whether to show error */
  protected readonly showError = computed(() => this.control()?.invalid && this.control()?.touched);
}

export const MaskedInputComponent = AfMaskedInputComponent;
