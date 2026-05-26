import { Component, ChangeDetectionStrategy, input } from '@angular/core';

import type { AfDescriptionItem } from './description-list.model';
export * from './description-list.model';



/**
 * Exibição de pares chave-valor para telas de detalhe.
 *
 * @example
 * ```html
 * <af-description-list [items]="[{term:'Nome',detail:'João'},{term:'Email',detail:'j@x.com'}]" />
 * ```
 */
@Component({
  selector: 'af-description-list',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './description-list.html',
})
export class AfDescriptionListComponent {
  /** Itens de dados (pares termo-detalhe) */
  readonly items = input<AfDescriptionItem[]>([]);

  /** Direção do layout */
  readonly layout = input<'vertical' | 'horizontal'>('vertical');
}
