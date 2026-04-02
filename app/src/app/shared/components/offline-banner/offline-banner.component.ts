import {
  type OnDestroy,
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  inject,
  signal,
} from '@angular/core';
import { AfBannerComponent } from '../banner/banner';
import { NetworkStatusService } from '../../../core/services/network-status.service';

/**
 * Offline banner shared component for the Shared module.
 * @selector app-offline-banner
 */
@Component({
  selector: 'app-offline-banner',
  standalone: true,
  imports: [AfBannerComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './offline-banner.component.html',
})
export class OfflineBannerComponent implements OnInit, OnDestroy {
  private networkStatus = inject(NetworkStatusService);

  isOnline = signal(true);

  ngOnInit(): void {
    this.isOnline.set(this.networkStatus.online);
    this.networkStatus.startMonitoring();
  }

  ngOnDestroy(): void {
    this.networkStatus.stopMonitoring();
  }

  retry(): void {
    window.location.reload();
  }
}
