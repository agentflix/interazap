import { Component, ChangeDetectionStrategy, input, output, signal, computed } from '@angular/core';
import { NgIcon } from '@ng-icons/core';

import type { AfTabItem } from './tabs.model';
export * from './tabs.model';



/**
 * Navegação por abas horizontais com indicador de sublinhado.
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
  /** Definições das abas */
  readonly tabs = input<AfTabItem[]>([]);

  /** ID da aba atualmente ativa */
  readonly activeTab = input('');

  /** Estica as abas para preencher a largura disponível de forma igual. */
  readonly fullWidth = input(false);

  /** Emitido ao clicar em uma aba */
  readonly tabChange = output<string>();

  protected selectTab(tab: AfTabItem): void {
    if (tab.disabled) return;
    this.tabChange.emit(tab.id);
  }
}
