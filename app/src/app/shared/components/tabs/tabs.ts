import { Component, ChangeDetectionStrategy, input, output, signal, computed } from '@angular/core';
import { NgIcon } from '@ng-icons/core';

import type { AfTabItem } from './tabs.model';
export * from './tabs.model';



/**
 * AfTabsComponent — Horizontal tab navigation with underline indicator.
 *
 * @example
 * ```html
 * <af-tabs [tabs]="tabs" [activeTab]="activeTab()" (tabChange)="onTabChange($event)" />
 * ```
 */
@Component({
  selector: 'af-tabs',
  standalone: true,
  imports: [NgIcon],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './tabs.html',
})
export class AfTabsComponent {
  /** Tab definitions */
  readonly tabs = input<AfTabItem[]>([]);

  /** Currently active tab id */
  readonly activeTab = input('');

  /** Stretch tabs to fill available width with equal distribution. */
  readonly fullWidth = input(false);

  /** Emitted when a tab is clicked */
  readonly tabChange = output<string>();

  protected selectTab(tab: AfTabItem): void {
    if (tab.disabled) return;
    this.tabChange.emit(tab.id);
  }
}
