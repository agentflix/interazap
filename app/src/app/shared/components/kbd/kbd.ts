import { Component, ChangeDetectionStrategy, input } from '@angular/core';

/**
 * Elemento de exibição de atalho de teclado.
 *
 * @example
 * ```html
 * <af-kbd>⌘</af-kbd> + <af-kbd>K</af-kbd>
 * ```
 */
@Component({
  selector: 'af-kbd',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './kbd.html',
})
export class AfKbdComponent {
  /** Rótulo acessível para leitores de tela */
  readonly ariaLabel = input<string>('');
}
