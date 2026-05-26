import {
  DestroyRef,
  type OnDestroy,
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  inject,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { AfBannerComponent } from '../banner/banner';
import { NetworkStatusService } from '../../../core/services/network-status.service';

/**
 * Banner exibido quando o navegador perde conexão com a internet.
 *
 * Monitora o status da rede via NetworkStatusService e exibe um aviso
 * com botão de recarregar a página quando a conexão é restaurada.
 */
@Component({
  selector: 'app-offline-banner',
  standalone: true,
  imports: [AfBannerComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './offline-banner.component.html',
})
export class OfflineBannerComponent implements OnInit, OnDestroy {
  private readonly networkStatus = inject(NetworkStatusService);
  private readonly destroyRef = inject(DestroyRef);

  /** Indica se o dispositivo está conectado à internet */
  isOnline = signal(true);

  ngOnInit(): void {
    this.isOnline.set(this.networkStatus.online);
    this.networkStatus.startMonitoring();

    this.networkStatus.statusChanges$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((online) => this.isOnline.set(online));
  }

  ngOnDestroy(): void {
    this.networkStatus.stopMonitoring();
  }

  /** Recarrega a página para tentar reconectar */
  retry(): void {
    window.location.reload();
  }
}
