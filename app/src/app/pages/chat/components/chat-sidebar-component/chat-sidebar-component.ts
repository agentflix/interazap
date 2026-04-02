import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { IconButtonComponent } from 'src/app/shared/components/buttons';

/**
 * Right action tabs extracted from chat page.
 */
@Component({
  selector: 'app-chat-page-sidebar',
  standalone: true,
  imports: [IconButtonComponent],
  templateUrl: './chat-sidebar-component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ChatSidebarComponent {
  readonly activeTab = input.required<'chat' | 'contact' | 'negotiation'>();
  readonly setTab = output<'chat' | 'contact' | 'negotiation'>();
}
