import { ChangeDetectionStrategy, Component } from '@angular/core';

/**
 * Chat page component for the Chat module.
 * @selector app-chat-page
 */
@Component({
  selector: 'app-chat-page',
  standalone: true,
  templateUrl: './chat-page.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ChatPageComponent {
  protected readonly isShellReady = true;
}
