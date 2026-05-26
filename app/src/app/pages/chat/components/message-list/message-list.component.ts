import { ChangeDetectionStrategy, Component } from '@angular/core';

/**
 * Componente de lista de mensagens do módulo de Chat.
 *
 * Stub reservado para implementação futura da listagem
 * de mensagens de um atendimento.
 */
@Component({
  selector: 'app-message-list',
  standalone: true,
  templateUrl: './message-list.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MessageListComponent {
  protected readonly isMessageListReady = true;
}
