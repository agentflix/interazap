import { type DestroyRef, Injectable, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Subject } from 'rxjs';
import { ChatRefreshService } from 'src/app/core/services/chat-refresh.service';
import { RealtimeService } from 'src/app/core/services/realtime.service';
import { tocarNotificacao } from 'src/app/shared/utils/notifications/chat-audio';
import type { IncomingMessageEvent } from '@chat/models/chat-realtime-listener.model';
export type { IncomingMessageEvent } from '@chat/models/chat-realtime-listener.model';


const CHAT_NOTIFICATION_SOUND_URL = '/assets/audio/chat-notification.mp3';
const MESSAGE_RECEIVED_EVENT = 'message.received';
const CHAT_ACTIVITY_EVENT = 'chat.activity';
const CHAT_NEW_TICKET_EVENT = 'chat.ticket.new';
const NOTIFICATION_COOLDOWN_MS = 600;
const TICKET_LIST_REFRESH_COOLDOWN_MS = 300;


interface MessageReceivedPayload {
  data?: {
    ticket_id?: string;
    direction?: string | null;
  };
}

interface ChatActivitySubeventPayload {
  type?: string;
}

interface ChatActivityPayload {
  subevents?: ChatActivitySubeventPayload[];
}

interface ChatNewTicketPayload {
  ticket_id?: string | number;
  ticket?: {
    id?: string | number;
  };
}

/**
 * Subscribes to realtime chat events (message.received, chat.activity,
 * chat.ticket.new) and triggers ticket-list refresh / notification sounds.
 *
 * Extracted from `Chat` host (FEAT-049) to keep the host thin.
 */
@Injectable({ providedIn: 'root' })
export class ChatRealtimeListenerService {
  private readonly realtime = inject(RealtimeService);
  private readonly chatRefresh = inject(ChatRefreshService);

  private readonly incomingMessageSubject = new Subject<IncomingMessageEvent>();
  readonly incomingMessage$ = this.incomingMessageSubject.asObservable();

  private lastNotificationAt = 0;
  private lastTicketListRefreshAt = 0;

  /**
   * Connects to realtime and starts listening to chat events.
   * Subscriptions are bound to the caller's DestroyRef, allowing the
   * service (singleton) to be re-subscribed cleanly after the host
   * component is destroyed and remounted.
   *
   * `realtime.connect()` is idempotent at the Socket.IO client level.
   */
  start(destroyRef: DestroyRef): void {
    this.realtime.connect();

    this.realtime
      .on<MessageReceivedPayload>(MESSAGE_RECEIVED_EVENT)
      .pipe(takeUntilDestroyed(destroyRef))
      .subscribe((event) => this.handleIncomingMessage(event));

    this.realtime
      .on<ChatActivityPayload>(CHAT_ACTIVITY_EVENT)
      .pipe(takeUntilDestroyed(destroyRef))
      .subscribe((event) => this.handleActivityEvent(event));

    this.realtime
      .on<ChatNewTicketPayload>(CHAT_NEW_TICKET_EVENT)
      .pipe(takeUntilDestroyed(destroyRef))
      .subscribe((event) => this.handleNewTicketEvent(event));
  }

  private handleIncomingMessage(event: MessageReceivedPayload | null): void {
    const payload = event?.data;
    if (!payload) return;

    const direction = (payload.direction ?? '').toLowerCase();
    if (direction && direction !== 'incoming') return;

    this.incomingMessageSubject.next({
      ticketId: payload.ticket_id ? String(payload.ticket_id) : null,
      contactId: null,
      direction,
    });

    const now = Date.now();
    if (now - this.lastNotificationAt < NOTIFICATION_COOLDOWN_MS) return;

    this.lastNotificationAt = now;
    void tocarNotificacao(CHAT_NOTIFICATION_SOUND_URL);
    this.chatRefresh.request();
  }

  private handleActivityEvent(event: ChatActivityPayload | null): void {
    const subevents = event?.subevents;
    if (!Array.isArray(subevents) || subevents.length === 0) return;

    const hasTicketMutation = subevents.some((subevent) => {
      const type = subevent?.type;
      return type === 'ticket.new' || type === 'ticket.updated' || type === 'chat.list.updated';
    });

    if (!hasTicketMutation) return;

    this.requestTicketListRefresh();
  }

  private handleNewTicketEvent(event: ChatNewTicketPayload | null): void {
    if (!event) return;

    const ticketId = event.ticket_id ?? event.ticket?.id;
    if (ticketId === null || ticketId === undefined || String(ticketId).trim() === '') return;

    this.requestTicketListRefresh();
  }

  private requestTicketListRefresh(): void {
    const now = Date.now();
    if (now - this.lastTicketListRefreshAt < TICKET_LIST_REFRESH_COOLDOWN_MS) {
      return;
    }

    this.lastTicketListRefreshAt = now;
    this.chatRefresh.request();
  }
}
