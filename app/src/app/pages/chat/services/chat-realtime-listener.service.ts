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
 * Escuta eventos realtime do chat (message.received, chat.activity, chat.ticket.new)
 * e dispara atualização da lista de tickets e sons de notificação.
 *
 * Extraído do host `Chat` (FEAT-049) para manter o host enxuto.
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
   * Conecta ao realtime e inicia a escuta dos eventos de chat.
   * As assinaturas ficam vinculadas ao DestroyRef do chamador, permitindo
   * re-assinatura limpa quando o componente host é destruído e remontado.
   *
   * `realtime.connect()` é idempotente no nível do cliente Socket.IO.
   *
   * @param destroyRef - Referência de destruição do componente chamador.
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
