import { DestroyRef, Injectable, inject, signal } from '@angular/core';
import { type Observable, Subject } from 'rxjs';
import { io, type Socket } from 'socket.io-client';
import { environment } from '@env/environment';
import { AuthStoreService } from './auth-store.service';

/**
 * Service for WebSocket-based real-time communication.
 *
 * @remarks
 * Manages Socket.IO connections for real-time events, room joining/leaving,
 * and slow network detection. Automatically disconnects on service destruction.
 *
 * @example
 * ```typescript
 * const rt = inject(RealtimeService);
 * rt.connect();
 * rt.on<MyEvent>('event-name').subscribe(data => ...);
 * ```
 */
@Injectable({ providedIn: 'root' })
export class RealtimeService {
  private readonly authStore = inject(AuthStoreService);
  private readonly destroyRef = inject(DestroyRef);

  constructor() {
    // Clean up resources when service is destroyed
    this.destroyRef.onDestroy(() => {
      this.disconnect();
    });
  }

  private socket: Socket | null = null;
  private readonly _connected = signal(false);
  private readonly eventSubjects = new Map<string, Subject<unknown>>();
  private readonly _lastEventTime = signal<number | null>(null);

  readonly connected = this._connected.asReadonly();
  readonly lastEventTime = this._lastEventTime.asReadonly();

  private slowNetworkTimer: ReturnType<typeof setTimeout> | null = null;
  private readonly SLOW_NETWORK_THRESHOLD_MS = 60_000;

  /**
   * Establishes a WebSocket connection using the auth token.
   *
   * @remarks
   * Idempotent - will not reconnect if already connected.
   * Reads token and tenant_id from AuthStoreService.
   */
  connect(): void {
    const token = this.authStore.token();
    const tenantId = this.authStore.user()?.tenant_id;
    if (!token || !tenantId) return;

    if (this.socket?.connected) {
      return;
    }

    const gatewayUrl =
      environment.gateway.url !== '' ? environment.gateway.url : window.location.origin;

    this.socket = io(gatewayUrl, {
      path: environment.gateway.path,
      auth: { token },
      transports: ['websocket', 'polling'],
      reconnection: true,
      reconnectionAttempts: 10,
      reconnectionDelay: 1000,
      reconnectionDelayMax: 5000,
      timeout: 20_000,
    });

    this.socket.on('connect', () => {
      this._connected.set(true);
      this._lastEventTime.set(Date.now());
      this.clearSlowNetworkTimer();
    });

    this.socket.on('disconnect', () => {
      this._connected.set(false);
    });

    this.socket.on('connect_error', (error) => {
      console.error('[RealtimeService] Connection error:', error.message);
      this._connected.set(false);
    });

    // Register event listeners for all subscribed events
    this.setupEventListeners();
  }

  /**
   * Internal: re-registers Socket.IO listeners for all subscribed events.
   */
  private setupEventListeners(): void {
    if (!this.socket) return;

    // Listen for all registered event names
    for (const [eventName, subject] of this.eventSubjects) {
      this.socket.on(eventName, (data: unknown) => {
        this._lastEventTime.set(Date.now());
        subject.next(data);
      });
    }
  }

  /**
   * Subscribes to a WebSocket event and returns an observable.
   *
   * @param eventName - Name of the event to listen for
   * @returns Observable emitting event data. Shares the underlying subject
   *          so multiple subscribers receive the same events.
   */
  on<T = unknown>(eventName: string): Observable<T> {
    let subject = this.eventSubjects.get(eventName);
    if (!subject) {
      subject = new Subject<unknown>();
      this.eventSubjects.set(eventName, subject);

      // Register immediately when socket exists, even before connect handshake,
      // to avoid dropping listeners subscribed right after connect().
      if (this.socket) {
        this.socket.on(eventName, (data: unknown) => {
          this._lastEventTime.set(Date.now());
          subject!.next(data);
        });
      }
    }

    return subject.asObservable() as Observable<T>;
  }

  /**
   * Join one or more WebSocket rooms.
   * The server validates tenant ownership before allowing the join.
   */
  joinRooms(rooms: string[]): void {
    if (!this.socket?.connected || rooms.length === 0) return;
    this.socket.emit('join', { rooms });
  }

  /**
   * Leave one or more WebSocket rooms.
   */
  leaveRooms(rooms: string[]): void {
    if (!this.socket?.connected || rooms.length === 0) return;
    this.socket.emit('leave', { rooms });
  }

  /**
   * Check if slow network feedback should be shown.
   * Returns true if no events have been received in SLOW_NETWORK_THRESHOLD_MS
   * while the connection appears active.
   */
  isSlowNetwork(): boolean {
    if (!this._connected()) return false;
    const lastEvent = this._lastEventTime();
    if (lastEvent === null) return false;
    return Date.now() - lastEvent > this.SLOW_NETWORK_THRESHOLD_MS;
  }

  /**
   * Start monitoring for slow network conditions.
   * Calls the callback after 60 seconds without events.
   */
  startSlowNetworkMonitor(callback: () => void): void {
    this.clearSlowNetworkTimer();
    this.slowNetworkTimer = setTimeout(() => {
      if (this.isSlowNetwork()) {
        callback();
      }
    }, this.SLOW_NETWORK_THRESHOLD_MS + 1000);
  }

  /**
   * Stop slow network monitoring.
   */
  stopSlowNetworkMonitor(): void {
    this.clearSlowNetworkTimer();
  }

  private clearSlowNetworkTimer(): void {
    if (this.slowNetworkTimer !== null) {
      clearTimeout(this.slowNetworkTimer);
      this.slowNetworkTimer = null;
    }
  }

  /**
   * Disconnects the WebSocket and clears all event subscriptions.
   */
  disconnect(): void {
    this.clearSlowNetworkTimer();
    this.socket?.disconnect();
    this.socket = null;
    this._connected.set(false);
    this.eventSubjects.forEach((subject) => subject.complete());
    this.eventSubjects.clear();
  }
}
