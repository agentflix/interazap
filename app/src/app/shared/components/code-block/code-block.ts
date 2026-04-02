import { Component, ChangeDetectionStrategy, input } from '@angular/core';

/**
 * AfCodeBlockComponent — Syntax-highlighted code block display.
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
  /** Code string to display */
  readonly code = input('');

  /** Optional language label */
  readonly label = input('');

  /** Language for syntax (future: highlighting) */
  readonly language = input('');
}
