import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';
import { AfKbdComponent } from '../kbd/kbd';

/**
 * Exibe uma combinação de atalho de teclado.
 *
 * @example
 * ```html
 * <af-shortcut-key [keys]="['⌘', 'Shift', 'P']" />
 * ```
 */
@Component({
  selector: 'af-shortcut-key',
  standalone: true,
  imports: [AfKbdComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './shortcut-key.html',
})
export class AfShortcutKeyComponent {
  /** Array de rótulos de teclas */
  readonly keys = input<string[]>([]);
}
