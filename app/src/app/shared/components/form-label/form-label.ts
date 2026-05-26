import { Component, ChangeDetectionStrategy, input } from '@angular/core';

/**
 * Átomo de rótulo de formulário — renderiza um &lt;label&gt; estilizado com indicador opcional de obrigatório.
 *
 * @example
 * ```html
 * <af-form-label label="Email" for="email-input" [required]="true" />
 * ```
 */
@Component({
  selector: 'af-form-label',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './form-label.html',
})
export class AfFormLabelComponent {
  /** Texto do rótulo a exibir */
  readonly label = input<string>();

  /** Atributo HTML for vinculado ao ID do campo */
  readonly for = input<string>();

  /** Exibe o asterisco vermelho de campo obrigatório */
  readonly required = input(false);
}
