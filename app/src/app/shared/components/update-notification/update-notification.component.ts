import { type OnInit, ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { UpdateService } from '../../../core/services/update.service';
import { AfButtonComponent } from '../button/button';

/**
 * Update notification shared component for the Shared module.
 * @selector app-update-notification
 */
@Component({
  selector: 'app-update-notification',
  standalone: true,
  imports: [AfButtonComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './update-notification.component.html',
  styleUrl: './update-notification.component.scss',
})
export class UpdateNotificationComponent implements OnInit {
  readonly updateService = inject(UpdateService);

  ngOnInit(): void {
    void this.updateService.checkForUpdates();
  }

  download(): void {
    void this.updateService.downloadUpdate();
  }

  dismiss(): void {
    this.updateService.available.set(false);
  }
}
