import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideMessagesSquare } from '@ng-icons/lucide';
import { ButtonComponent } from 'src/app/shared/components/buttons';

/**
 * Estado vazio da thread de chat.
 *
 * Exibido quando não há mensagens carregadas para o atendimento.
 * Opcionalmente renderiza um botão para iniciar a conversa.
 */
@Component({
  selector: 'app-user-chat-empty-state',
  standalone: true,
  imports: [NgIcon, ButtonComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  viewProviders: [provideIcons({ lucideMessagesSquare })],
  templateUrl: './user-chat-empty-state.component.html',
})
export class UserChatEmptyStateComponent {
  readonly showAction = input(false);
  readonly description = input('Nenhuma mensagem encontrada.');
  readonly startConversation = output<void>();
}
