import { Component, ChangeDetectionStrategy, input } from '@angular/core';

/**
 * Átomo de mensagem de erro de formulário — exibe um erro de validação abaixo de um campo.
 *
 * @example
 * ```html
 * <af-form-error [show]="showError()" message="Campo obrigatório." />
 * ```
 */
@Component({
  selector: 'af-form-error',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './form-error.html',
})
export class AfFormErrorComponent {
  /** Indica se a mensagem de erro deve ser exibida */
  readonly show = input(false);

  /** Texto da mensagem de erro */
  readonly message = input('Campo obrigatório.');
}
