import { Component, ChangeDetectionStrategy, input, computed } from '@angular/core';
import { AfKbdComponent } from '../kbd/kbd';

/**
 * AfShortcutKeyComponent — Displays a keyboard shortcut combination.
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
  /** Array of key labels */
  readonly keys = input<string[]>([]);
}
