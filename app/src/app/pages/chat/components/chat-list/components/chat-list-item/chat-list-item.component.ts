import { ChangeDetectionStrategy, Component, input, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideUser, lucideUsers } from '@ng-icons/lucide';
import { type Called } from 'src/app/core/services/called.service';
import { ChatListStateService } from '../../services/chat-list-state.service';

/**
 * Linha de ticket na listagem lateral do chat.
 *
 * @remarks
 * Exibe as informacoes resumidas de um ticket incluindo nome do contato,
 * ultima mensagem, badges de sentimento e contagem de nao lidos.
 *
 * @example
 * ```html
 * <app-chat-list-item [called]="ticket" [active]="isActive" />
 * ```
 */
@Component({
  selector: 'app-chat-list-item',
  standalone: true,
  imports: [RouterLink, NgIcon],
  templateUrl: './chat-list-item.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: {
    class: 'block',
  },
  viewProviders: [
    provideIcons({
      lucideUser,
      lucideUsers,
    }),
  ],
})
export class ChatListItemComponent {
  readonly called = input.required<Called>();
  readonly active = input(false);

  readonly state = inject(ChatListStateService);
}
