import { ChangeDetectionStrategy, Component, OnInit, effect, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { NgxSonnerToaster } from 'ngx-sonner';
import { NativeBridgeService } from './core/services/platform/native-bridge.service';
import { BackButtonService } from './core/services/platform/back-button.service';
import { DeepLinkService } from './core/services/platform/deep-link.service';
import { RealtimeService } from './core/services/realtime.service';
import { AuthStoreService } from './core/services/auth-store.service';
import { PushService } from './core/services/platform/push.service';
import { OfflineBannerComponent } from './shared/components/offline-banner/offline-banner.component';
import { UpdateNotificationComponent } from './shared/components/update-notification/update-notification.component';

/**
 * Root application component for InteraZap.
 * Provides the main layout with offline banner, router outlet for page content,
 * toast notifications via ngx-sonner, and update notifications.
 *
 * No mobile (Capacitor), inicializa o {@link NativeBridgeService} para configurar
 * status bar, splash screen, keyboard e listener de ciclo de vida do app.
 */
@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet, NgxSonnerToaster, OfflineBannerComponent, UpdateNotificationComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './app.html',
})
export class AppComponent implements OnInit {
  private readonly nativeBridge = inject(NativeBridgeService);
  private readonly backButton = inject(BackButtonService);
  private readonly deepLink = inject(DeepLinkService);
  private readonly realtime = inject(RealtimeService);
  private readonly authStore = inject(AuthStoreService);
  private readonly pushService = inject(PushService);

  private pushInitializedForSession = false;

  /** Application title identifier */
  title = 'interazap-new';

  constructor() {
    effect(() => {
      const isAuthenticated = this.authStore.isAuthenticated();

      if (!isAuthenticated) {
        this.pushInitializedForSession = false;
        return;
      }

      if (this.pushInitializedForSession) {
        return;
      }

      this.pushInitializedForSession = true;
      void this.pushService.initializeAfterLogin();
    });
  }

  ngOnInit(): void {
    // Fire-and-forget: native bridge faz no-op em web e não pode bloquear bootstrap
    void this.nativeBridge.initialize();
    // Fire-and-forget: listener Android do botão físico voltar (no-op em web/iOS)
    void this.backButton.initialize();
    // Fire-and-forget: registra listener de deep links (Universal Links / App Links)
    void this.deepLink.initialize();
    this.nativeBridge.onAppResume(() => {
      this.realtime.connect();
    });
  }
}
