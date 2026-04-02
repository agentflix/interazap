import { type OnDestroy, Injectable, inject, signal, computed } from '@angular/core';
import { type Socket, io } from 'socket.io-client';
import { environment } from '@env/environment';
import { AuthStoreService } from './auth-store.service';
import { ChatRealtimeStore } from './chat-realtime.store';
import {
  type ChatActivityEvent,
  type ChatActivitySubevent,
  type ChatActivitySubeventType,
  type ChatMessageDeleteEvent,
  type ChatMessageEditEvent,
  type ChatMessageReactionEvent,
  type ChatMessageStatusEvent,
  type ChatNewMessageEvent,
  type ChatNewTicketEvent,
  type ChatTicketSentimentUpdatedEvent,
  type ChatTicketUpdatedEvent,
  type ChatTypingEvent,
  type IntegrationConnectionEvent,
} from './chat-realtime.events';

export type {
  ChatActivityEvent,
  ChatActivitySubevent,
  ChatActivitySubeventType,
  ChatMessageDeleteEvent,
  ChatMessageEditEvent,
  ChatMessageReactionEvent,
  ChatMessageStatusEvent,
  ChatNewMessageEvent,
  ChatNewTicketEvent,
  ChatTicketSentimentUpdatedEvent,
  ChatTicketUpdatedEvent,
  ChatTypingEvent,
  IntegrationConnectionEvent,
} from './chat-realtime.events';

/**
 * Serviço responsável por gerenciar a comunicação em tempo real via WebSockets (Socket.io).
 *
 * Centraliza a escuta de eventos de chat, como novas mensagens, alteração de status
 * e indicadores de digitação, além de gerenciar a conexão por tenant e ticket.
 *
 * @class ChatRealtimeService
 * @description Service de infraestrutura para eventos realtime com Socket.io.
 */
@Injectable({ providedIn: 'root' })
export class ChatRealtimeService implements OnDestroy {
  private static readonly MESSAGE_DEDUP_TTL_MS = 30000;

  private readonly authStore = inject(AuthStoreService);
  private readonly realtimeStore = inject(ChatRealtimeStore);
  private socket: Socket | null = null;
  private currentTicketRoom: string | null = null;
  private lastTenantRoom: string | null = null;
  private readonly _connected = signal(false);

  readonly connected = this._connected.asReadonly();

  // Private signals for event streams (last event of each type)
  private readonly _messageStatus = signal<ChatMessageStatusEvent | null>(null);
  private readonly _newMessage = signal<ChatNewMessageEvent | null>(null);
  private readonly _typing = signal<ChatTypingEvent | null>(null);
  private readonly _reaction = signal<ChatMessageReactionEvent | null>(null);
  private readonly _edit = signal<ChatMessageEditEvent | null>(null);
  private readonly _delete = signal<ChatMessageDeleteEvent | null>(null);
  private readonly _newTicket = signal<ChatNewTicketEvent | null>(null);
  private readonly _ticketUpdated = signal<ChatTicketUpdatedEvent | null>(null);
  private readonly _ticketSentimentUpdated = signal<ChatTicketSentimentUpdatedEvent | null>(null);
  private readonly _activity = signal<ChatActivityEvent | null>(null);
  private readonly _contactUpdated = signal<{ contact_id: string; data: unknown } | null>(null);
  private readonly _dealUpdated = signal<{ deal_id: string; data: unknown } | null>(null);
  private readonly _integrationConnection = signal<IntegrationConnectionEvent | null>(null);
  private readonly _integrationConnectionIncoming = signal<IntegrationConnectionEvent | null>(null);

  // Event counters for tracking changes (used with computed to create reactive triggers)
  private readonly _messageStatusCount = signal(0);
  private readonly _newMessageCount = signal(0);
  private readonly _typingCount = signal(0);
  private readonly _reactionCount = signal(0);
  private readonly _editCount = signal(0);
  private readonly _deleteCount = signal(0);
  private readonly _newTicketCount = signal(0);
  private readonly _ticketUpdatedCount = signal(0);
  private readonly _ticketSentimentUpdatedCount = signal(0);
  private readonly _activityCount = signal(0);
  private readonly _contactUpdatedCount = signal(0);
  private readonly _dealUpdatedCount = signal(0);
  private readonly _integrationConnectionCount = signal(0);
  private readonly _integrationConnectionIncomingCount = signal(0);

  private readonly pendingMessagesById = new Map<string, ChatNewMessageEvent>();
  private readonly processedMessageIds = new Map<string, number>();

  // Public readonly signals for consumers (tuple: [event, version] para forçar reatividade)
  readonly messageStatus = computed(() => ({
    event: this._messageStatus(),
    version: this._messageStatusCount(),
  }));
  readonly newMessage = computed(() => ({
    event: this._newMessage(),
    version: this._newMessageCount(),
  }));
  readonly typing = computed(() => ({ event: this._typing(), version: this._typingCount() }));
  readonly reaction = computed(() => ({ event: this._reaction(), version: this._reactionCount() }));
  readonly edit = computed(() => ({ event: this._edit(), version: this._editCount() }));
  readonly delete = computed(() => ({ event: this._delete(), version: this._deleteCount() }));
  readonly newTicket = computed(() => ({
    event: this._newTicket(),
    version: this._newTicketCount(),
  }));
  readonly ticketUpdated = computed(() => ({
    event: this._ticketUpdated(),
    version: this._ticketUpdatedCount(),
  }));
  readonly ticketSentimentUpdated = computed(() => ({
    event: this._ticketSentimentUpdated(),
    version: this._ticketSentimentUpdatedCount(),
  }));
  readonly activity = computed(() => ({ event: this._activity(), version: this._activityCount() }));
  readonly contactUpdated = computed(() => ({
    event: this._contactUpdated(),
    version: this._contactUpdatedCount(),
  }));
  readonly dealUpdated = computed(() => ({
    event: this._dealUpdated(),
    version: this._dealUpdatedCount(),
  }));
  readonly integrationConnection = computed(() => ({
    event: this._integrationConnection(),
    version: this._integrationConnectionCount(),
  }));
  readonly integrationConnectionIncoming = computed(() => ({
    event: this._integrationConnectionIncoming(),
    version: this._integrationConnectionIncomingCount(),
  }));

  /**
   * Estabelece conexão com o servidor de WebSockets e entra no canal do Tenant.
   * @param tenantId Identificador do tenant para escuta de eventos globais.
   */
  connect(tenantId?: string | null): void {
    if (typeof window === 'undefined') return;

    const token = this.authStore.token();
    if (typeof token !== 'string' || token.trim() === '') return;

    this.lastTenantRoom = this.resolveTenantRoom(tenantId);

    if (this.reconnectExistingSocket(token)) {
      return;
    }

    this.createSocket(token);
  }

  private resolveTenantRoom(tenantId?: string | null): string | null {
    const userTenantId = this.authStore.user()?.tenant_id;
    const normalizedTenantId =
      typeof tenantId === 'string' && tenantId.trim().length > 0
        ? tenantId.trim()
        : typeof userTenantId === 'string' && userTenantId.trim().length > 0
          ? userTenantId.trim()
          : null;

    return normalizedTenantId !== null ? `tenant:${normalizedTenantId}` : null;
  }

  private reconnectExistingSocket(token: string): boolean {
    if (this.socket === null) {
      return false;
    }

    if (!this.socket.connected && !this.socket.active) {
      this.socket.auth = { token };
      this.socket.connect();
    }

    return true;
  }

  private createSocket(token: string): void {
    const gatewayConfig = environment.gateway ?? {
      url: '',
      path: '/ws',
    };

    const gatewayUrl = gatewayConfig.url !== '' ? gatewayConfig.url : window.location.origin;

    this.socket = io(gatewayUrl, {
      path: gatewayConfig.path,
      auth: { token },
      transports: ['websocket'],
      reconnection: true,
      reconnectionAttempts: Infinity,
      reconnectionDelay: 1000,
      reconnectionDelayMax: 10000,
      randomizationFactor: 0.3,
      timeout: 10000,
    });

    this.socket.on('connect', () => {
      this._connected.set(true);
      // Auto-join tenant room (já feito no Gateway)
      if (this.lastTenantRoom !== null) {
        this.socket?.emit('join', { rooms: [this.lastTenantRoom] });
      }
      // Re-join ticket room se existir
      if (this.currentTicketRoom !== null) {
        this.socket?.emit('join', { rooms: [this.currentTicketRoom] });
      }
    });

    this.socket.on('disconnect', (reason) => {
      this._connected.set(false);
      // Se o server desconectou, o socket.io não reconecta automaticamente
      if (reason === 'io server disconnect') {
        this.socket?.connect();
      }
    });

    this.socket.on('connect_error', (error: Error) => {
      this._connected.set(false);

      const message = String(error?.message ?? '').toLowerCase();
      const isAuthError =
        message.includes('401') ||
        message.includes('unauthorized') ||
        message.includes('authentication');

      if (isAuthError) {
        this.socket?.disconnect();
      }
    });

    this.bindEventListeners();
  }

  /**
   * Finaliza a conexão ativa com o WebSocket e limpa canais e estados.
   */
  disconnect(): void {
    this.socket?.disconnect();
    this.socket = null;
    this.currentTicketRoom = null;
    this._connected.set(false);
    this._integrationConnectionIncoming.set(null);
    this.pendingMessagesById.clear();
    this.processedMessageIds.clear();
    this.realtimeStore.clear();
  }

  /**
   * Inscreve o cliente em um canal privado de um atendimento (ticket) específico.
   * @param ticketId Identificador do ticket.
   */
  joinTicket(ticketId: string): void {
    if (this.socket?.connected !== true) {
      this.connect();
    }

    const ticketRoom = `ticket:${ticketId}`;
    if (this.currentTicketRoom === ticketRoom) return;

    if (this.currentTicketRoom !== null) {
      this.socket?.emit('leave', { rooms: [this.currentTicketRoom] });
    }

    this.currentTicketRoom = ticketRoom;
    this.socket?.emit('join', { rooms: [ticketRoom] });
  }

  /**
   * Sai do canal de um atendimento específico.
   * @param ticketId Identificador do ticket.
   */
  leaveTicket(ticketId: string): void {
    const ticketRoom = `ticket:${ticketId}`;
    if (this.currentTicketRoom === ticketRoom) {
      this.socket?.emit('leave', { rooms: [ticketRoom] });
      this.currentTicketRoom = null;
    }
  }

  private bindEventListeners(): void {
    if (!this.socket) return;

    // Tenant-level events
    this.socket.on('chat.ticket.new', (event: ChatNewTicketEvent) => {
      this.realtimeStore.push({ type: 'newTicket', payload: event });
      this._newTicket.set(event);
      this._newTicketCount.update((c) => c + 1);
    });

    this.socket.on('chat.ticket.updated', (event: ChatTicketUpdatedEvent) => {
      this._ticketUpdated.set(event);
      this._ticketUpdatedCount.update((c) => c + 1);
    });

    this.socket.on('ticket.sentiment_updated', (event: ChatTicketSentimentUpdatedEvent) => {
      this._ticketSentimentUpdated.set(event);
      this._ticketSentimentUpdatedCount.update((c) => c + 1);
    });

    // Ticket-level events
    this.socket.on('chat.message.status', (event: ChatMessageStatusEvent) => {
      this.realtimeStore.push({ type: 'status', payload: event });
      this._messageStatus.set(event);
      this._messageStatusCount.update((c) => c + 1);
    });

    this.socket.on('chat.message.new', (event: ChatNewMessageEvent) => {
      this.handleNewMessageEvent(event);
    });

    this.socket.on('chat.typing', (event: ChatTypingEvent) => {
      this.realtimeStore.push({ type: 'typing', payload: event });
      if (event.is_typing) {
        setTimeout(() => {
          const current = this.realtimeStore.typingByTicket();
          if (current.get(event.ticket_id) === event) {
            this.realtimeStore.setTyping({ ...event, is_typing: false });
          }
        }, 10000);
      }
      this._typing.set(event);
      this._typingCount.update((c) => c + 1);
    });

    this.socket.on('chat.message.reaction', (event: ChatMessageReactionEvent) => {
      this.realtimeStore.push({ type: 'reaction', payload: event });
      this._reaction.set(event);
      this._reactionCount.update((c) => c + 1);
    });

    this.socket.on('chat.message.edit', (event: ChatMessageEditEvent) => {
      this.realtimeStore.push({ type: 'edit', payload: event });
      this._edit.set(event);
      this._editCount.update((c) => c + 1);
    });

    this.socket.on('chat.message.delete', (event: ChatMessageDeleteEvent) => {
      this.realtimeStore.push({ type: 'delete', payload: event });
      this._delete.set(event);
      this._deleteCount.update((c) => c + 1);
    });

    // Evento unificado chat.activity (v2.0)
    // Processa subevents sequencialmente para garantir consistência
    this.socket.on('chat.activity', (event: ChatActivityEvent) => {
      this.processActivityEvent(event);
    });

    this.socket.on('integration.connection', (event: IntegrationConnectionEvent) => {
      this.publishIntegrationConnectionIncoming(this.normalizeIntegrationConnectionEvent(event));
    });
  }

  /**
   * Normalizes raw integration connection event fields.
   */
  private normalizeIntegrationConnectionEvent(
    event: IntegrationConnectionEvent,
  ): IntegrationConnectionEvent {
    return {
      tenant_id: event.tenant_id ?? null,
      instance_id: event.instance_id ?? null,
      token: event.token ?? null,
      status: typeof event.status === 'string' ? event.status.trim().toLowerCase() : undefined,
      connected: event.connected,
      qrcode: typeof event.qrcode === 'string' ? event.qrcode.trim() : null,
      paircode: typeof event.paircode === 'string' ? event.paircode.trim() : null,
    };
  }

  /** Receives normalized integration.connection events before adapter buffering. */
  private publishIntegrationConnectionIncoming(event: IntegrationConnectionEvent): void {
    this._integrationConnectionIncoming.set(event);
    this._integrationConnectionIncomingCount.update((c) => c + 1);
  }

  /** Receives buffered integration.connection events from the integration adapter. */
  publishBufferedIntegrationConnection(event: IntegrationConnectionEvent): void {
    this._integrationConnection.set(event);
    this._integrationConnectionCount.update((c) => c + 1);
  }

  /**
   * Processa o evento unificado chat.activity e distribui subevents para handlers específicos.
   * Mantém retrocompatibilidade com eventos individuais.
   *
   * @param event Evento chat.activity com subevents
   * @private
   */
  private processActivityEvent(event: ChatActivityEvent): void {
    // Notifica listeners do evento completo
    this._activity.set(event);
    this._activityCount.update((c) => c + 1);

    // Processa cada subevent sequencialmente
    for (const subevent of event.subevents) {
      this.processSubevent(subevent, event);
    }
  }

  /**
   * Processa um subevent individual do chat.activity.
   *
   * @param subevent Subevent a processar
   * @param parent Evento pai com contexto (ticketId, timestamp)
   * @private
   */
  private processSubevent(subevent: ChatActivitySubevent, parent: ChatActivityEvent): void {
    const handler = this.subeventHandlers[subevent.type];
    if (handler === undefined) {
      return;
    }

    handler(subevent.data, parent);
  }

  private readonly subeventHandlers: Partial<
    Record<ChatActivitySubeventType, (data: unknown, parent: ChatActivityEvent) => void>
  > = {
    'msg.received': (data, parent) => {
      const msgEvent = this.normalizeNewMessageEvent(data as ChatNewMessageEvent, parent);
      this.handleNewMessageEvent(msgEvent);
    },
    'msg.status': (data) => {
      const statusEvent = data as ChatMessageStatusEvent;
      this.realtimeStore.push({ type: 'status', payload: statusEvent });
      this._messageStatus.set(statusEvent);
      this._messageStatusCount.update((c) => c + 1);
    },
    'msg.reaction': (data) => {
      const reactionEvent = data as ChatMessageReactionEvent;
      this.realtimeStore.push({ type: 'reaction', payload: reactionEvent });
      this._reaction.set(reactionEvent);
      this._reactionCount.update((c) => c + 1);
    },
    'msg.edit': (data) => {
      const editEvent = data as ChatMessageEditEvent;
      this.realtimeStore.push({ type: 'edit', payload: editEvent });
      this._edit.set(editEvent);
      this._editCount.update((c) => c + 1);
    },
    'msg.delete': (data) => {
      const deleteEvent = data as ChatMessageDeleteEvent;
      this.realtimeStore.push({ type: 'delete', payload: deleteEvent });
      this._delete.set(deleteEvent);
      this._deleteCount.update((c) => c + 1);
    },
    'chat.list.updated': (data, parent) => {
      const ticketEvent = {
        ticket_id: parent.ticketId ?? '',
        ticket: data,
      } as ChatTicketUpdatedEvent;
      this._ticketUpdated.set(ticketEvent);
      this._ticketUpdatedCount.update((c) => c + 1);
    },
    'ticket.new': (data) => {
      const newTicketEvent = data as ChatNewTicketEvent;
      this.realtimeStore.push({ type: 'newTicket', payload: newTicketEvent });
      this._newTicket.set(newTicketEvent);
      this._newTicketCount.update((c) => c + 1);
    },
    'ticket.updated': (data) => {
      const updatedEvent = data as ChatTicketUpdatedEvent;
      this._ticketUpdated.set(updatedEvent);
      this._ticketUpdatedCount.update((c) => c + 1);
    },
    'contact.updated': (data) => {
      const contactEvent = data as { contact_id: string; data: unknown };
      this._contactUpdated.set(contactEvent);
      this._contactUpdatedCount.update((c) => c + 1);
    },
    'deal.updated': (data) => {
      const dealEvent = data as { deal_id: string; data: unknown };
      this._dealUpdated.set(dealEvent);
      this._dealUpdatedCount.update((c) => c + 1);
    },
    'negotiation.status.changed': (data) => {
      const negotiationEvent = data as {
        negotiation_id?: string;
        deal_id?: string;
        [key: string]: unknown;
      };
      const dealId = negotiationEvent.deal_id ?? negotiationEvent.negotiation_id;
      if (typeof dealId === 'string' && dealId.trim() !== '') {
        this._dealUpdated.set({ deal_id: dealId, data: negotiationEvent });
        this._dealUpdatedCount.update((c) => c + 1);
      }
    },
  };

  /**
   * Verifica se há alguém digitando em um atendimento específico através do cache local.
   * @param ticketId Identificador do ticket.
   * @returns {ChatTypingEvent | undefined} Dados de digitação se ativo.
   */
  isTyping(ticketId: string): ChatTypingEvent | undefined {
    return this.realtimeStore.typingByTicket().get(ticketId);
  }

  ngOnDestroy(): void {
    // Signals não precisam de cleanup manual - são automaticamente garbage collected
    this.disconnect();
  }

  /**
   * Normaliza o evento de nova mensagem com contexto do evento pai quando necessário.
   */
  private normalizeNewMessageEvent(
    event: ChatNewMessageEvent,
    parent: ChatActivityEvent,
  ): ChatNewMessageEvent {
    const parentTicketId =
      typeof parent.ticketId === 'string' && parent.ticketId.trim() !== ''
        ? parent.ticketId
        : typeof parent.chatId === 'string' && parent.chatId.trim() !== ''
          ? parent.chatId
          : '';

    const ticketId =
      typeof event.ticket_id === 'string' && event.ticket_id.trim() !== ''
        ? event.ticket_id
        : parentTicketId;

    return {
      ...event,
      ticket_id: ticketId,
    };
  }

  /**
   * Aplica estratégia de reconciliação entre fast-path e chat.activity.
   */
  private handleNewMessageEvent(event: ChatNewMessageEvent): void {
    const messageId = this.resolveMessageId(event);
    this.pruneProcessedMessageIds();

    if (messageId !== null && this.wasRecentlyProcessed(messageId)) {
      return;
    }

    const hasTicketId = typeof event.ticket_id === 'string' && event.ticket_id.trim() !== '';
    if (!hasTicketId) {
      if (messageId !== null) {
        this.pendingMessagesById.set(messageId, event);
      }
      return;
    }

    this.realtimeStore.push({ type: 'new', payload: event });
    this._newMessage.set(event);
    this._newMessageCount.update((c) => c + 1);

    if (messageId !== null) {
      this.pendingMessagesById.delete(messageId);
      this.markMessageAsProcessed(messageId);
    }
  }

  /**
   * Resolve identificador da mensagem para deduplicação.
   */
  private resolveMessageId(event: ChatNewMessageEvent): string | null {
    const messageId = event.message.id;
    return typeof messageId === 'string' && messageId.trim() !== '' ? messageId : null;
  }

  /**
   * Marca uma mensagem como processada para evitar duplicidades em janela curta.
   */
  private markMessageAsProcessed(messageId: string): void {
    this.processedMessageIds.set(messageId, Date.now());
  }

  /**
   * Verifica se a mensagem já foi processada recentemente.
   */
  private wasRecentlyProcessed(messageId: string): boolean {
    const processedAt = this.processedMessageIds.get(messageId);
    if (processedAt === undefined) {
      return false;
    }

    return Date.now() - processedAt <= ChatRealtimeService.MESSAGE_DEDUP_TTL_MS;
  }

  /**
   * Limpa entries expiradas do cache de deduplicação.
   */
  private pruneProcessedMessageIds(): void {
    const now = Date.now();

    for (const [messageId, processedAt] of this.processedMessageIds.entries()) {
      if (now - processedAt > ChatRealtimeService.MESSAGE_DEDUP_TTL_MS) {
        this.processedMessageIds.delete(messageId);
      }
    }
  }
}
