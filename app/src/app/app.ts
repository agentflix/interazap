import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { NgxSonnerToaster } from 'ngx-sonner';
import { OfflineBannerComponent } from './shared/components/offline-banner/offline-banner.component';
import { UpdateNotificationComponent } from './shared/components/update-notification/update-notification.component';

/**
 * Root application component for AgentFlix.
 * Provides the main layout with offline banner, router outlet for page content,
 * toast notifications via ngx-sonner, and update notifications.
 */
@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet, NgxSonnerToaster, OfflineBannerComponent, UpdateNotificationComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './app.html',
})
export class AppComponent {
  /** Application title identifier */
  title = 'agentflix-new';
}
