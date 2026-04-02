import { ChangeDetectionStrategy, Component } from '@angular/core';

/**
 * User chat typing indicator component for the Chat module.
 * @selector app-user-chat-typing-indicator
 */
@Component({
  selector: 'app-user-chat-typing-indicator',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './user-chat-typing-indicator.component.html',
})
export class UserChatTypingIndicatorComponent {
  protected readonly label = 'Digitando...';
}
