import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideCheck, lucideFilter } from '@ng-icons/lucide';
import { AfTabsComponent } from 'src/app/shared/components/tabs/tabs';
import { ChatListStateService } from '../../services/chat-list-state.service';

/**
 * Cabecalho de filtros da listagem de tickets.
 *
 * @remarks
 * Exibe abas de filtro por status (Fila, Abertos, Todos) e
 * gerencia a interacao com o ChatListStateService.
 *
 * @example
 * ```html
 * <app-chat-list-filters />
 * ```
 */
@Component({
  selector: 'app-chat-list-filters',
  standalone: true,
  imports: [AfTabsComponent, NgIcon],
  templateUrl: './chat-list-filters.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: {
    class: 'block w-full',
  },
  viewProviders: [
    provideIcons({
      lucideFilter,
      lucideCheck,
    }),
  ],
})
export class ChatListFiltersComponent {
  readonly state = inject(ChatListStateService);
}
