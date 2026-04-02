import { Component, ChangeDetectionStrategy, input } from '@angular/core';

/**
 * AfCardComponent — Versatile container card with optional header, body, and footer slots.
 *
 * @example
 * ```html
 * <af-card>
 *   <div header><h3>Title</h3></div>
 *   <p>Card content goes here.</p>
 *   <div footer><af-button>Action</af-button></div>
 * </af-card>
 * ```
 */
@Component({
  selector: 'af-card',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { class: 'block', '[class.h-full]': 'fullHeight()' },
  templateUrl: './card.html',
})
export class AfCardComponent {
  /** Apply default padding to card body */
  readonly padded = input(true);

  /** Stretch to parent height (for grid alignment) */
  readonly fullHeight = input(false);
}
