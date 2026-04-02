import { Component, ChangeDetectionStrategy, input, output, signal, computed } from '@angular/core';

import type { AfColorOption } from './color-palette.model';
export * from './color-palette.model';



/**
 * AfColorPaletteComponent — Selectable color palette.
 *
 * @example
 * ```html
 * <af-color-palette [colors]="brandColors" [selected]="selectedColor()" (colorSelected)="onColor($event)" />
 * ```
 */
@Component({
  selector: 'af-color-palette',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './color-palette.html',
})
export class AfColorPaletteComponent {
  /** Available color options */
  readonly colors = input<AfColorOption[]>([]);

  /** Currently selected color value */
  readonly selected = input('');

  /** Emitted when a color is selected */
  readonly colorSelected = output<string>();
}
