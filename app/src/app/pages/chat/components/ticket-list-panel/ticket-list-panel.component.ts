import { ChangeDetectionStrategy, Component } from '@angular/core';

/**
 * Ticket list panel component for the Chat module.
 * @selector app-ticket-list-panel
 */
@Component({
  selector: 'app-ticket-list-panel',
  standalone: true,
  templateUrl: './ticket-list-panel.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TicketListPanelComponent {
  protected readonly isListReady = true;
}
