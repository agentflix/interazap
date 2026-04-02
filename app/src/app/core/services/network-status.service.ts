import { Injectable, inject, signal, NgZone } from '@angular/core';
import { fromEvent, merge } from 'rxjs';
import { map } from 'rxjs/operators';

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
  private ngZone = inject(NgZone);

  private isOnline = signal(navigator.onLine);
  private checkInterval: ReturnType<typeof setInterval> | null = null;

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
    // Use RxJS merge for cross-browser compatibility
    const online$ = fromEvent(window, 'online').pipe(map(() => true));
    const offline$ = fromEvent(window, 'offline').pipe(map(() => false));

    merge(online$, offline$).subscribe((online) => {
      this.ngZone.run(() => {
        this.isOnline.set(online);
      });
    });

    // Heartbeat check every 30 seconds
    this.checkInterval = setInterval(() => {
      void this.checkConnection();
    }, 30000);
  }

  /**
   * Stops periodic network health checks and clears the interval.
   */
  stopMonitoring(): void {
    if (this.checkInterval !== null) {
      clearInterval(this.checkInterval);
      this.checkInterval = null;
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
      this.isOnline.set(response.ok);
    } catch {
      this.isOnline.set(false);
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
}
