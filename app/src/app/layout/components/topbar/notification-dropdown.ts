import {
  Component,
  ChangeDetectionStrategy,
  signal,
  inject,
  DestroyRef,
  computed,
  ChangeDetectorRef,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { LucideAngularModule } from 'lucide-angular';
import { AfScrollAreaComponent } from '../../../shared/components/scroll-area/scroll-area';
import { NotificationApiService } from '../../../core/services/notification-api.service';
import { RealtimeService } from '../../../core/services/realtime.service';
import { type Notification, NotificationTypeEnum } from '../../../shared/models/notification.model';

/**
 * Notification bell dropdown that fetches real notifications from the API.
 * Displays unread count, loading/empty/error states, and supports marking as read.
 *
 * @example
 * ```html
 * <af-notification-dropdown />
 * ```
 */
@Component({
  selector: 'af-notification-dropdown',
  standalone: true,
  imports: [LucideAngularModule, AfScrollAreaComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './notification-dropdown.html',
  host: {
    '(document:click)': 'onDocumentClick($event)',
  },
})
export class NotificationDropdownComponent {
  private readonly api = inject(NotificationApiService);
  private readonly realtime = inject(RealtimeService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly cdr = inject(ChangeDetectorRef);

  readonly open = signal(false);
  readonly notifications = signal<Notification[]>([]);
  readonly loading = signal(false);
  readonly error = signal<string | null>(null);

  readonly unreadCount = computed(() => this.notifications().filter((n) => !n.read_at).length);

  constructor() {
    // Connect to WebSocket if not already connected
    this.realtime.connect();
    // Subscribe to notification events
    this.subscribeToNotificationEvents();
  }

  /**
   * Subscribe to real-time notification events via WebSocket.
   *
   * @note Do NOT guard with `if (!this.realtime.connected())` here.
   * RealtimeService.on() buffers events internally and registers the socket
   * listener as soon as the connection is established. Guarding would prevent
   * the Observable subscription from being created when the component
   * initializes before the socket is connected.
   */
  private subscribeToNotificationEvents(): void {
    this.realtime
      .on<{ notification: Notification }>('notification.new')
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (event) => {
          if (event?.notification) {
            // Deduplicate by ID
            const exists = this.notifications().some((n) => n.id === event.notification!.id);
            if (!exists) {
              this.notifications.update((list) => [event.notification!, ...list]);
              // Trigger change detection for OnPush strategy
              this.cdr.detectChanges();
            }
          }
        },
        error: (err) => {
          console.error('[NotificationDropdown] WebSocket error:', err);
        },
      });
  }

  /**
   * Toggle dropdown open/close state and load notifications if opening.
   */
  toggle(): void {
    const next = !this.open();
    this.open.set(next);
    if (next) {
      this.loadNotifications();
    }
  }

  /**
   * Fetch notifications from the API.
   */
  loadNotifications(): void {
    this.loading.set(true);
    this.error.set(null);

    this.api
      .fetchUnread(10)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.notifications.set(response.data);
          this.loading.set(false);
        },
        error: () => {
          this.error.set('Não foi possível carregar as notificações.');
          this.loading.set(false);
        },
      });
  }

  /**
   * Handle notification click: mark as read and optionally navigate.
   *
   * @param notification - The clicked notification
   */
  handleNotificationClick(notification: Notification): void {
    if (!notification.read_at) {
      this.api
        .markAsRead(notification.id)
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe({
          next: () => {
            this.notifications.update((list) =>
              list.map((n) =>
                n.id === notification.id ? { ...n, read_at: new Date().toISOString() } : n,
              ),
            );
          },
        });
    }

    // Close dropdown after click
    this.open.set(false);
  }

  /**
   * Map notification type string to a Lucide icon name.
   *
   * @param type - Notification type string
   * @returns Lucide icon name
   */
  protected getIconForType(type: string): string {
    switch (type) {
      case NotificationTypeEnum.NewTicket:
        return 'ticket';
      case NotificationTypeEnum.TicketAssigned:
        return 'user-check';
      case NotificationTypeEnum.TicketClosed:
        return 'check-circle';
      case NotificationTypeEnum.System:
        return 'info';
      case NotificationTypeEnum.Billing:
        return 'credit-card';
      default:
        return 'bell';
    }
  }

  /**
   * Format a date string to a relative time label.
   *
   * @param dateString - ISO date string
   * @returns Human-readable relative time
   */
  protected formatTime(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Agora';
    if (diffMins < 60) return `${diffMins}m atrás`;
    if (diffHours < 24) return `${diffHours}h atrás`;
    if (diffDays < 7) return `${diffDays}d atrás`;
    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
  }

  /** Close dropdown when clicking outside. */
  protected onDocumentClick(event: Event): void {
    const el = event.target as HTMLElement;
    if (!el.closest('af-notification-dropdown')) {
      this.open.set(false);
    }
  }
}
