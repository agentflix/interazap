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
 * Campo de checkbox estilizado com rótulo inline e integração com FormControl.
 *
 * Usa um checkbox visual customizado (input nativo oculto + div sobreposto) para
 * garantir que o estado marcado fique visível mesmo quando definido programaticamente
 * (ex.: selectAll / operações em lote).
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

  /** FormControl do checkbox */
  readonly control = input.required<FormControl<boolean>>();

  /** Texto do rótulo inline */
  readonly label = input<string>('');

  /** Atributo data-test para testes E2E */
  readonly dataTest = input<string>();

  /** aria-label para acessibilidade quando não há rótulo visível */
  readonly ariaLabel = input<string>();

  /** Classe CSS do contêiner */
  readonly classContainer = input<string | null>(null);

  /** Ativa/desativa o espaçamento vertical padrão */
  readonly spacing = input(true);

  /** Texto auxiliar exibido abaixo do campo */
  readonly helpText = input<string>();

  /** Mensagem de erro quando o controle é inválido */
  readonly errorMessage = input('Campo obrigatório.');

  /** ID único para associação label-input */
  protected readonly checkboxId = `checkbox-${Math.random().toString(36).slice(2, 9)}`;

  /** Estado marcado reativo — atualizado via subscription no valueChanges */
  protected readonly isChecked = signal(false);

  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  protected readonly showError = computed(() => this.control().invalid && this.control().touched);

  /** Classes dinâmicas do checkbox baseadas no estado marcado */
  protected readonly boxClasses = () => {
    const base = [
      'mt-0.5 size-4 shrink-0 rounded border cursor-pointer',
      'flex items-center justify-center',
      'transition-colors duration-150',
      'focus:outline-none focus:ring-2 focus:ring-accent-500/30 focus:ring-offset-0',
    ];
    const state = this.isChecked()
      ? 'bg-accent-500 border-accent-500'
      : 'bg-white dark:bg-neutral-900 border-neutral-300 dark:border-white/[0.14]';
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

  /** Alterna o valor do controle quando o checkbox ou rótulo é clicado */
  protected toggle(): void {
    if (this.control().disabled) return;
    this.control().setValue(!this.control().value);
    this.control().markAsTouched();
  }

  /** Mantém o checkbox nativo sincronizado quando o usuário interage diretamente */
  protected onNativeChange(event: Event): void {
    const checked = (event.target as HTMLInputElement).checked;
    this.isChecked.set(checked);
  }
}

export const CheckboxInputComponent = AfCheckboxInputComponent;
