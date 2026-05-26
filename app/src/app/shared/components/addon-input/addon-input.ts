import { Component, ChangeDetectionStrategy, input, output, computed } from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Campo de texto com botão addon à esquerda (ícone ou texto clicável).
 *
 * Ideal para ações do tipo "Adicionar", seletores de prefixo ou entradas
 * acionadas por ícone. O botão addon emite o evento `addonClick`.
 *
 * Contexto: utilizado em formulários de tags, filtros rápidos e qualquer
 * campo que necessite de uma ação contextual anexada à entrada.
 *
 * @example
 * ```html
 * <af-addon-input
 *   [control]="tagControl"
 *   label="Tag"
 *   addonIcon="plus"
 *   addonLabel="Adicionar"
 *   placeholder="Nova tag..."
 *   (addonClick)="onAddTag()"
 * />
 * ```
 */
@Component({
  selector: 'af-addon-input',
  standalone: true,
  imports: [ReactiveFormsModule, AfFormLabelComponent, AfFormErrorComponent, LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './addon-input.html',
})
export class AfAddonInputComponent {
  /** FormControl do campo de texto */
  readonly control = input.required<FormControl<string>>();

  /** Texto do rótulo */
  readonly label = input<string>();

  /** Classe CSS do container */
  readonly classContainer = input<string>('mb-4');

  /** Tipo do input HTML */
  readonly type = input<string>('text');

  /** Texto de placeholder */
  readonly placeholder = input('');

  /** Exibe asterisco de campo obrigatório no rótulo */
  readonly required = input(false);

  /** Mensagem de erro de validação */
  readonly errorMessage = input('Campo obrigatório.');

  /** Nome do ícone Lucide para o botão addon */
  readonly addonIcon = input<string>();

  /** Texto do botão addon */
  readonly addonLabel = input<string>();

  /** Atributo data-test para testes E2E */
  readonly dataTest = input<string>();

  /** Emitido ao clicar no botão addon */
  readonly addonClick = output<void>();

  /** ID único para associar label e input */
  protected readonly inputId = `addon-${Math.random().toString(36).slice(2, 9)}`;

  /** Classes CSS do botão addon */
  protected readonly addonClasses = computed(() =>
    [
      'inline-flex items-center gap-1.5 px-3',
      'bg-neutral-100 dark:bg-neutral-800',
      'border border-r-0 border-neutral-300 dark:border-neutral-600',
      'rounded-l-md text-sm font-medium',
      'text-neutral-600 dark:text-neutral-300',
      'hover:bg-neutral-200 dark:hover:bg-neutral-700',
      'transition-colors duration-150 cursor-pointer',
      'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/30',
    ].join(' '),
  );

  /** Classes CSS do campo de texto */
  protected readonly inputClasses = computed(() => {
    const borderColor = this.showError()
      ? 'border-red-500 dark:border-red-400'
      : 'border-neutral-300 dark:border-neutral-600';

    return [
      'flex-1 h-10 px-3 text-sm',
      'bg-white dark:bg-neutral-900',
      'text-neutral-900 dark:text-neutral-50',
      'placeholder:text-neutral-400 dark:placeholder:text-neutral-500',
      'border rounded-r-md',
      'focus:outline-none focus:ring-2 focus:ring-accent-500/30 focus:border-accent-500',
      'transition-colors duration-150',
      borderColor,
    ].join(' ');
  });

  /** Indica se o erro de validação deve ser exibido */
  protected readonly showError = computed(() => this.control()?.invalid && this.control()?.touched);
}
