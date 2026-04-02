import { Component, ChangeDetectionStrategy, input } from '@angular/core';

import type { AfDescriptionItem } from './description-list.model';
export * from './description-list.model';



/**
 * AfDescriptionListComponent — Key-value pair display for detail views.
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
  /** Data items */
  readonly items = input<AfDescriptionItem[]>([]);

  /** Layout direction */
  readonly layout = input<'vertical' | 'horizontal'>('vertical');
}
