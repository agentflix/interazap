import { ChangeDetectionStrategy, Component, computed, input, output } from '@angular/core';
import { NgIcon } from '@ng-icons/core';
import { type Called, type CalledSentiment } from 'src/app/core/services/called.service';
import { IconButtonComponent } from 'src/app/shared/components/buttons';
import { AfScrollAreaComponent } from 'src/app/shared/components/scroll-area/scroll-area';
import { AfTabsComponent, type AfTabItem } from 'src/app/shared/components/tabs/tabs';
import { ChatTicketItemComponent } from '../../components/chat-ticket-item/chat-ticket-item';

/**
 * Painel esquerdo de listagem de atendimentos extraído do container de chat.
 *
 * @remarks
 * Reduz o acoplamento da página principal delegando a renderização da lista,
 * abas de filtro, busca, menu de emergência e seleção de ticket para este componente.
 * Toda lógica de estado permanece no container pai via inputs/outputs.
 */
@Component({
  selector: 'app-chat-page-list',
  standalone: true,
  imports: [
    NgIcon,
    IconButtonComponent,
    AfTabsComponent,
    AfScrollAreaComponent,
    ChatTicketItemComponent,
  ],
  templateUrl: './chat-list-component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ChatListComponent {
  readonly isSoundMuted = input.required<boolean>();
  readonly isRefreshing = input.required<boolean>();
  readonly searchTerm = input.required<string>();
  readonly isEmergencyMenuOpen = input.required<boolean>();
  readonly emergencyFilter = input<CalledSentiment | null>(null);
  readonly ticketTabs = input.required<AfTabItem[]>();
  readonly ticketFilter = input.required<'pending' | 'open' | 'all'>();
  readonly loadingTickets = input.required<boolean>();
  readonly visibleTickets = input.required<readonly Called[]>();
  readonly selectedTicketId = input<string | null>(null);

  readonly toggleSound = output<void>();
  readonly refreshList = output<void>();
  readonly clearSearch = output<void>();
  readonly openSearchModal = output<void>();
  readonly toggleEmergencyMenu = output<void>();
  readonly closeEmergencyMenu = output<void>();
  readonly setEmergencyFilter = output<CalledSentiment | null>();
  readonly openNewConversationModal = output<void>();
  readonly setTicketFilter = output<string>();
  readonly selectTicket = output<string | number>();

  readonly selectedTicketIdComputed = computed(() => this.selectedTicketId());

  isTicketSelected(ticketId: string | number): boolean {
    return this.selectedTicketIdComputed() === String(ticketId);
  }
}
