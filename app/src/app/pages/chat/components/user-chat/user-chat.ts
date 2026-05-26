import {
  ChangeDetectionStrategy,
  Component,
  EventEmitter,
  Output,
  ViewChild,
  input,
} from '@angular/core';
import { type CalledMessage } from 'src/app/core/services/called-message.service';
import { UserChatThreadComponent } from '../user-chat-thread/user-chat-thread.component';

/**
 * Componente de chat do usuário — wrapper da thread de mensagens.
 *
 * @remarks
 * Encapsula o `UserChatThreadComponent` e expõe os métodos
 * `appendMessage` e `replaceMessage` para que o container pai
 * possa inserir ou atualizar mensagens sem recarregar a thread.
 */
@Component({
  selector: 'app-user-chat',
  standalone: true,
  imports: [UserChatThreadComponent],
  templateUrl: './user-chat.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: {
    class: 'block h-full overflow-hidden',
  },
})
export class UserChat {
  @ViewChild(UserChatThreadComponent)
  private readonly threadRef?: UserChatThreadComponent;

  readonly allowStartConversation = input<boolean>(true);
  readonly ticketId = input<string | null | undefined>(undefined);
  readonly canLoadMessages = input<boolean>(true);
  readonly readOnlyMode = input<boolean>(false);

  @Output() reply = new EventEmitter<CalledMessage>();

  onReply(message: CalledMessage): void {
    this.reply.emit(message);
  }

  appendMessage(message: CalledMessage): void {
    this.threadRef?.appendMessage(message);
  }

  replaceMessage(tempId: string, message: CalledMessage): void {
    this.threadRef?.replaceMessage(tempId, message);
  }
}
