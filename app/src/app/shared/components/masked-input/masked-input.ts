import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { AfMaskDirective, type AfMaskPreset } from '../../directives/mask.directive';
import { resolveInputContainerClass } from '../input-container.util';

/**
 * Campo com máscara automática para formatos brasileiros:
 * CPF, CNPJ, CPF/CNPJ auto-detect, CEP, telefone e moeda (R$).
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
  /** FormControl do campo */
  readonly control = input.required<FormControl<string>>();

  /** Predefinição de máscara a aplicar */
  readonly mask = input.required<AfMaskPreset | string>();

  /** Texto do rótulo */
  readonly label = input<string>();

  /** Classe CSS do contêiner */
  readonly classContainer = input<string | null>(null);

  /** Ativa/desativa o espaçamento vertical padrão */
  readonly spacing = input(true);

  /** Texto auxiliar exibido abaixo do campo */
  readonly helpText = input<string>();

  /** Exibe asterisco de campo obrigatório no rótulo */
  readonly required = input(false);

  /** Mensagem de erro customizada */
  readonly errorMessage = input('Campo inválido.');

  /** Placeholder customizado (sobrescreve o gerado automaticamente) */
  readonly placeholder = input<string>();

  /** Atributo data-test */
  readonly dataTest = input<string>();

  /** Tamanho do campo */
  readonly size = input<'sm' | 'md'>('md');

  /** ID único do campo */
  protected readonly inputId = `masked-${Math.random().toString(36).slice(2, 9)}`;

  /** Classes CSS do contêiner */
  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  /** Placeholder gerado automaticamente baseado na predefinição de máscara */
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

  /** Classes CSS dinâmicas do campo */
  protected readonly inputClasses = computed(() => {
    const sizeClasses = this.size() === 'sm' ? 'h-8 px-2.5 text-xs' : 'h-10 px-3 text-sm';

    const borderColor = this.showError()
      ? 'border-red-500 dark:border-red-400'
      : 'border-neutral-300 dark:border-white/[0.14]';

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

  /** Indica se o erro deve ser exibido */
  protected readonly showError = computed(() => this.control()?.invalid && this.control()?.touched);
}

export const MaskedInputComponent = AfMaskedInputComponent;
