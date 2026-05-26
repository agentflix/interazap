import { Component, ChangeDetectionStrategy, input, output, signal, computed } from '@angular/core';

import type { AfColorOption } from './color-palette.model';
export * from './color-palette.model';



/**
 * Paleta de cores selecionável.
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
  /** Opções de cor disponíveis */
  readonly colors = input<AfColorOption[]>([]);

  /** Valor da cor atualmente selecionada */
  readonly selected = input('');

  /** Emitido quando uma cor é selecionada */
  readonly colorSelected = output<string>();
}
