import { Component, ChangeDetectionStrategy, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Item de navegação para sidebars.
 *
 * @example
 * ```html
 * <af-sidebar-item icon="home" label="Dashboard" [active]="true" href="/dashboard" />
 * ```
 */
@Component({
  selector: 'af-sidebar-item',
  standalone: true,
  imports: [LucideAngularModule, RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './sidebar-item.html',
})
export class AfSidebarItemComponent {
  /** Nome do ícone Lucide */
  readonly icon = input<string>();

  /** Rótulo do item */
  readonly label = input('');

  /** Link de navegação */
  readonly href = input('#');

  /** Indica se este item está ativo */
  readonly active = input(false);

  /** Indica se a sidebar está recolhida (modo somente ícone) */
  readonly collapsed = input(false);

  /** Contagem opcional do badge */
  readonly badge = input<number | string>();
}
