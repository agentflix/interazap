import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';
import { type FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfFormLabelComponent } from '../form-label/form-label';
import { AfFormErrorComponent } from '../form-error/form-error';
import { resolveInputContainerClass } from '../input-container.util';

import type { AfRadioOption } from './radio-input.model';
export * from './radio-input.model';



/**
 * Grupo de botões de rádio estilizado com layout horizontal ou vertical.
 *
 * @example
 * ```html
 * <af-radio-input
 *   [control]="statusControl"
 *   [options]="statusOptions"
 *   name="status"
 *   label="Status"
 *   orientation="horizontal"
 * />
 * ```
 */
@Component({
  selector: 'af-radio-input',
  standalone: true,
  imports: [ReactiveFormsModule, AfFormLabelComponent, AfFormErrorComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './radio-input.html',
})
export class AfRadioInputComponent {
  /** FormControl do grupo de rádio */
  readonly control = input.required<FormControl<string>>();

  /** Opções disponíveis */
  readonly options = input.required<AfRadioOption[]>();

  /** Atributo name do grupo de rádio HTML */
  readonly name = input.required<string>();

  /** Rótulo do grupo */
  readonly label = input<string>();

  /** Exibe asterisco de campo obrigatório */
  readonly required = input(false);

  /** Orientação do layout */
  readonly orientation = input<'horizontal' | 'vertical'>('vertical');

  /** Classe CSS do contêiner */
  readonly classContainer = input<string | null>(null);

  /** Ativa/desativa o espaçamento vertical padrão */
  readonly spacing = input(true);

  /** Texto auxiliar exibido abaixo do campo */
  readonly helpText = input<string>();

  /** Mensagem de erro */
  readonly errorMessage = input('Selecione uma opção.');

  /** Atributo data-test para testes E2E */
  readonly dataTest = input<string>();

  /** Classes CSS do contêiner */
  protected readonly containerClasses = computed(() =>
    resolveInputContainerClass(this.classContainer(), this.spacing()),
  );

  /** Direção flex do grupo baseada na orientação */
  protected readonly groupClasses = computed(() => {
    const base = 'flex gap-3';
    return this.orientation() === 'horizontal' ? `${base} flex-row flex-wrap` : `${base} flex-col`;
  });

  /** Classes de cada rótulo de opção */
  protected optionClasses(option: AfRadioOption): string {
    const base = 'inline-flex items-center gap-2.5 select-none';
    const cursor = option.disabled ? 'cursor-not-allowed' : 'cursor-pointer';
    return `${base} ${cursor}`;
  }

  /** Indica se o erro deve ser exibido */
  protected readonly showError = computed(() => this.control()?.invalid && this.control()?.touched);
}
