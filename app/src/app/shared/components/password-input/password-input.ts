import { Component, ChangeDetectionStrategy, input, computed, signal, output } from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { toSignal, toObservable } from '@angular/core/rxjs-interop';
import { EMPTY, merge, map, switchMap } from 'rxjs';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { LucideAngularModule } from 'lucide-angular';
import { resolveInputContainerClass } from '../input-container.util';

import type { AfInputSize } from './password-input.model';
export * from './password-input.model';

/**
 * Campo de senha com alternância de visibilidade via ícone de olho.
 *
 * Ideal para formulários de login, cadastro e configurações.
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
  /** FormControl da senha */
  readonly control = input.required<FormControl<string>>();

  /** Texto do rótulo */
  readonly label = input<string>();

  /** Classe CSS do contêiner */
  readonly classContainer = input<string | null>(null);

  /** Ativa/desativa o espaçamento vertical padrão */
  readonly spacing = input(true);

  /** Texto auxiliar exibido abaixo do campo */
  readonly helpText = input<string>();

  /** Texto placeholder */
  readonly placeholder = input('••••••••');

  /** Exibe asterisco de campo obrigatório no rótulo */
  readonly required = input(false);

  /** Mensagem de erro */
  readonly errorMessage = input('Senha é obrigatória.');

  /** Atributo data-test */
  readonly dataTest = input<string>();

  /** Tamanho do campo: sm para compacto, md para o padrão */
  readonly size = input<AfInputSize>('md');

  /** Desativa o preenchimento automático do navegador */
  readonly disableAutofill = input(false);

  /** Emitido quando o campo recebe foco */
  readonly focusEmitter = output<void>();

  /** Emitido quando o campo perde o foco */
  readonly blurEmitter = output<void>();

  /** Atributo autocomplete para controle do preenchimento automático */
  readonly autocomplete = input<string>('off');

  // Tracks status/value changes so computed signals react to setErrors() / markAsTouched()
  private readonly _controlChanges = toSignal(
    toObservable(this.control).pipe(
      switchMap((ctrl) => (ctrl ? merge(ctrl.statusChanges, ctrl.valueChanges).pipe(map(() => null)) : EMPTY)),
    ),
    { initialValue: null },
  );

  /** Estado de visibilidade da senha */
  protected readonly visible = signal(false);

  /** ID único do campo */
  protected readonly inputId = `password-${Math.random().toString(36).slice(2, 9)}`;

  /** Classes CSS do contêiner */
  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  /** Classes CSS dinâmicas do campo */
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

  /** Indica se o erro deve ser exibido — depende de _controlChanges para reagir a setErrors/markAsTouched */
  protected readonly showError = computed(() => {
    this._controlChanges();
    return this.control()?.invalid && this.control()?.touched;
  });

  /** Alterna a visibilidade da senha */
  protected toggleVisibility(): void {
    this.visible.update((v) => !v);
  }
}
