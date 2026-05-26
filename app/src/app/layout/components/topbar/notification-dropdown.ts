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
 * Dropdown do sino de notificações — busca notificações reais da API.
 * Exibe contagem de não lidas, estados de carregamento/vazio/erro e suporta marcar como lida.
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
   * Inscreve-se em eventos de notificação em tempo real via WebSocket.
   *
   * @note NÃO proteja com `if (!this.realtime.connected())` aqui.
   * `RealtimeService.on()` armazena eventos internamente e registra o listener
   * assim que a conexão é estabelecida. Proteger impediria a criação da inscrição
   * quando o componente inicializa antes do socket estar conectado.
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

  /** Alterna o estado aberto/fechado do dropdown e carrega notificações ao abrir. */
  toggle(): void {
    const next = !this.open();
    this.open.set(next);
    if (next) {
      this.loadNotifications();
    }
  }

  /** Busca as notificações não lidas da API. */
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
   * Trata o clique em uma notificação: marca como lida e fecha o dropdown.
   * @param notification Notificação clicada
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
   * Mapeia o tipo de notificação para um nome de ícone Lucide.
   * @param type String do tipo de notificação
   * @returns Nome do ícone Lucide correspondente
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
   * Formata uma data ISO para um rótulo de tempo relativo legível.
   * @param dateString String de data ISO 8601
   * @returns Tempo relativo legível (ex: "5m atrás", "2h atrás")
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

  /** Fecha o dropdown quando o usuário clica fora do componente. */
  protected onDocumentClick(event: Event): void {
    const el = event.target as HTMLElement;
    if (!el.closest('af-notification-dropdown')) {
      this.open.set(false);
    }
  }
}
