import { Injectable, inject, signal, NgZone } from '@angular/core';
import { Network } from '@capacitor/network';
import type { Subscription} from 'rxjs';
import { Subject, fromEvent, merge } from 'rxjs';
import { map } from 'rxjs/operators';
import { PlatformService } from './platform/platform.service';

/**
 * Monitora o status de conectividade de rede.
 *
 * @remarks
 * Combina eventos online/offline do navegador com polling heartbeat
 * para determinar o status da rede. Executa fora da zona Angular para desempenho.
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
   * Retorna sincronamente o status de conexão atual.
   */
  get online(): boolean {
    return this.isOnline();
  }

  /**
   * Inicia o monitoramento do status de rede via eventos do navegador e heartbeat periódico.
   *
   * @remarks
   * Registra listeners de online/offline e faz ping em `/api/health` a cada 30 segundos.
   * Executa callbacks fora da zona Angular para evitar change detection desnecessário.
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
   * Para as verificações periódicas de saúde da rede e limpa o intervalo.
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
   * Interno: realiza requisição HEAD em `/api/health` para verificar conectividade.
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
   * Retorna o status de conexão atual como uma Promise.
   *
   * @returns Promise resolvendo com o estado de conexão atual
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
