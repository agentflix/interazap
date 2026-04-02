import { ChangeDetectionStrategy, Component } from '@angular/core';

/**
 * Conversation panel component for the Chat module.
 * @selector app-conversation-panel
 */
@Component({
  selector: 'app-conversation-panel',
  standalone: true,
  templateUrl: './conversation-panel.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ConversationPanelComponent {
  protected readonly isConversationReady = true;
}
