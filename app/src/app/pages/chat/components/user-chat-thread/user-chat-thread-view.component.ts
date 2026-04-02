import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { type CalledMessage } from 'src/app/core/services/called-message.service';
import { UserChatMessageBubbleComponent } from './user-chat-message-bubble.component';

/**
 * User chat thread view component for the Chat module.
 * @selector app-user-chat-thread-view
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
