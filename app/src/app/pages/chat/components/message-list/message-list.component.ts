import { ChangeDetectionStrategy, Component } from '@angular/core';

/**
 * Message list component for the Chat module.
 * @selector app-message-list
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
