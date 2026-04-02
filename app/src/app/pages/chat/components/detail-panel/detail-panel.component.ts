import { ChangeDetectionStrategy, Component } from '@angular/core';

/**
 * Detail panel component for the Chat module.
 * @selector app-detail-panel
 */
@Component({
  selector: 'app-detail-panel',
  standalone: true,
  templateUrl: './detail-panel.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DetailPanelComponent {
  protected readonly isDetailReady = true;
}
