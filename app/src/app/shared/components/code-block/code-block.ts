import { Component, ChangeDetectionStrategy, input } from '@angular/core';

/**
 * Bloco de código com destaque de sintaxe.
 *
 * @example
 * ```html
 * <af-code-block [code]="jsonString" language="json" />
 * ```
 */
@Component({
  selector: 'af-code-block',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './code-block.html',
})
export class AfCodeBlockComponent {
  /** String de código a ser exibida */
  readonly code = input('');

  /** Rótulo opcional do bloco */
  readonly label = input('');

  /** Linguagem para destaque de sintaxe (futuro) */
  readonly language = input('');
}
