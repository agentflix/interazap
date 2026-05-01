import { Injectable, inject, signal, NgZone } from '@angular/core';
import { Network } from '@capacitor/network';
import { Subject, Subscription, fromEvent, merge } from 'rxjs';
import { map } from 'rxjs/operators';
import { PlatformService } from './platform/platform.service';

/**
 * Service for monitoring network connectivity status.
 *
 * @remarks
 * Uses browser online/offline events combined with heartbeat polling
 * to determine network status. Runs outside Angular zone for performance.
 *
 * @example
 * ```typescript
 * const network = inject(NetworkStatusService);
 * network.startMonitoring();
 * if (network.online) { ... }
 * ```
 */
@Injectable({
  providedIn: 'root',
})
export class NetworkStatusService {
  private readonly ngZone = inject(NgZone);
  private readonly platform = inject(PlatformService);

  private isOnline = signal(navigator.onLine);
  private checkInterval: ReturnType<typeof setInterval> | null = null;
  private browserEventsSubscription: Subscription | null = null;
  private nativeListenerHandle: { remove: () => Promise<void> } | null = null;
  private monitoringStarted = false;

  private readonly statusChangesSubject = new Subject<boolean>();
  readonly statusChanges$ = this.statusChangesSubject.asObservable();

  /**
   * Synchronously returns current online status.
   */
  get online(): boolean {
    return this.isOnline();
  }

  /**
   * Starts monitoring network status via browser events and periodic heartbeat.
   *
   * @remarks
   * Registers online/offline event listeners and pings /api/health every 30 seconds.
   * Runs callbacks outside Angular zone to avoid unnecessary change detection.
   */
  startMonitoring(): void {
    if (this.monitoringStarted) {
      return;
    }
    this.monitoringStarted = true;

    // Use RxJS merge for cross-browser compatibility
    const online$ = fromEvent(window, 'online').pipe(map(() => true));
    const offline$ = fromEvent(window, 'offline').pipe(map(() => false));

    this.browserEventsSubscription = merge(online$, offline$).subscribe((online) => {
      this.ngZone.run(() => {
        this.setOnlineState(online);
      });
    });

    if (this.platform.isMobile) {
      void this.bindNativeListener();
    }

    // Heartbeat check every 30 seconds
    this.checkInterval = setInterval(() => {
      void this.checkConnection();
    }, 30000);
  }

  /**
   * Stops periodic network health checks and clears the interval.
   */
  stopMonitoring(): void {
    this.monitoringStarted = false;

    if (this.checkInterval !== null) {
      clearInterval(this.checkInterval);
      this.checkInterval = null;
    }

    this.browserEventsSubscription?.unsubscribe();
    this.browserEventsSubscription = null;

    if (this.nativeListenerHandle !== null) {
      void this.nativeListenerHandle.remove();
      this.nativeListenerHandle = null;
    }
  }

  /**
   * Internal: performs a HEAD request to /api/health to verify connectivity.
   */
  private async checkConnection(): Promise<void> {
    try {
      // Try to fetch a small resource to verify connection
      const response = await fetch('/api/health', {
        method: 'HEAD',
        cache: 'no-store',
      });
      this.setOnlineState(response.ok);
    } catch {
      this.setOnlineState(false);
    }
  }

  /**
   * Returns current online status as a Promise.
   *
   * @returns Promise resolving to current online state
   */
  isOnlineAsync(): Promise<boolean> {
    return new Promise((resolve) => {
      resolve(this.isOnline());
    });
  }

  markOnline(): void {
    this.setOnlineState(true);
  }

  markOffline(): void {
    this.setOnlineState(false);
  }

  private async bindNativeListener(): Promise<void> {
    try {
      const status = await Network.getStatus();
      this.setOnlineState(status.connected);

      this.nativeListenerHandle = await Network.addListener('networkStatusChange', (status) => {
        this.ngZone.run(() => {
          this.setOnlineState(status.connected);
        });
      });
    } catch {
      // Capacitor Network pode não estar disponível em alguns ambientes de teste
    }
  }

  private setOnlineState(online: boolean): void {
    if (this.isOnline() === online) {
      return;
    }

    this.isOnline.set(online);
    this.statusChangesSubject.next(online);
  }
}
