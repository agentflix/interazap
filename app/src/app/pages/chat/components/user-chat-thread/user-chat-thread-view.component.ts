import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { type CalledMessage } from 'src/app/core/services/called-message.service';
import { UserChatMessageBubbleComponent } from './user-chat-message-bubble.component';

/**
 * Componente de visualização da thread de mensagens.
 *
 * Renderiza a lista de bolhas de mensagem para o atendimento ativo,
 * delegando cada item ao `UserChatMessageBubbleComponent`.
 */
@Component({
  selector: 'app-user-chat-thread-view',
  standalone: true,
  imports: [UserChatMessageBubbleComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './user-chat-thread-view.component.html',
})
export class UserChatThreadViewComponent {
  readonly messages = input.required<CalledMessage[]>();
  readonly readOnlyMode = input(false);

  readonly reply = output<CalledMessage>();
}
