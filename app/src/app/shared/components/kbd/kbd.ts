import { Component, ChangeDetectionStrategy, input } from '@angular/core';

/**
 * AfKbdComponent — Keyboard shortcut display element.
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
  readonly ariaLabel = input<string>('');
}
