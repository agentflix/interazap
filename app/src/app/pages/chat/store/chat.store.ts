import { computed, inject, Injectable, signal, untracked, type OnDestroy } from '@angular/core';
import {
  type Observable,
  type Subscription,
  EMPTY,
  catchError,
  expand,
  map,
  of,
  reduce,
} from 'rxjs';
import { CalledService, type Called, type CalledCounts } from '@core/services/called.service';
import {
  CalledMessageService,
  type CalledMessage,
  type CalledMessageListOptions,
  type CalledMessageQuotedMessage,
} from '@core/services/called-message.service';
import { type ChatMessageEditEvent } from '@core/services/chat-realtime.service';
import {
  ChatMessageCacheService,
  type ChatMessageCacheDelegate,
} from '@core/services/chat-message-cache.service';
import { compareMessagesDesc, resolveMessageOrderTimestamp as resolveStableOrderTimestamp } from '@core/utils/message-comparator.util';
import { type ChatStoreState } from './chat-store.model';

interface StreamingState {
  runId: string;
  text: string;
  isFinal: boolean;
  updatedAt: number;
}

type AiProcessingStatus = 'idle' | 'queued' | 'running' | 'completed' | 'failed' | 'rejected';

interface AiProcessingState {
  runId: string | null;
  status: AiProcessingStatus;
  messageId: string | null;
  error: string | null;
  updatedAt: number;
}

interface BatchChanges {
  tickets: Map<string, Called>;
  messages: Map<string, CalledMessage[]>;
  streaming: Map<string, StreamingState>;
  aiProcessing: Map<string, AiProcessingState>;
  delta: CalledCounts;
  needsResync: Set<string>;
}

interface BatchFlags {
  hasTicketChanges: boolean;
  hasMessageChanges: boolean;
  hasStreamingChanges: boolean;
  hasAiProcessingChanges: boolean;
  hasDeltaChanges: boolean;
}

/** Typed payload for ticket.new / ticket.updated events. */
interface TicketEventData {
  ticket?: Called;
  ticket_id?: string;
}

/** Typed payload for msg.received / msg.sent events. */
interface MessageEventData extends Partial<CalledMessage> {
  ticket_id?: string;
  message?: CalledMessage;
}

/**
 * Event structure for realtime adapter events.
 */
export interface ChatRealtimeAdapterEvent {
  type: string;
  data: unknown;
  timestamp: number;
}

/**
 * Chat store managing ticket list state, messages, and realtime updates.
 *
 * @remarks
 * Handles the full ticket list state including filtering, pagination, and LRU cache management.
 * Processes realtime events (new tickets, messages, edits, status changes) and applies
 * batched updates to minimize change detection cycles.
 *
 * @example
 * ```typescript
 * const chatStore = inject(ChatStore);
 * chatStore.selectTicket('ticket-123');
 * ```
 */
@Injectable({
  providedIn: 'root',
})
export class ChatStore implements OnDestroy, ChatMessageCacheDelegate {
  private static readonly MESSAGE_STATUS_RANK: Record<string, number> = {
    pending: 0,
    sent: 1,
    delivered: 2,
    read: 3,
    failed: 4,
    deleted: 5,
  };

  private readonly calledService = inject(CalledService);
  private readonly calledMessageService = inject(CalledMessageService);
  private readonly messageCacheService = inject(ChatMessageCacheService);
  private resyncSub: Subscription | null = null;
  private resyncMessagesSub: Subscription | null = null;

  constructor() {
    this.messageCacheService.setDelegate(this);
  }

  // ChatMessageCacheDelegate implementation

  /** Returns messages for a ticket from the central store. */
  getMessages(ticketId: string): CalledMessage[] {
    return this.messages().get(ticketId) ?? [];
  }

  /** Sets messages for a ticket in the central store. */
  setMessages(ticketId: string, msgs: CalledMessage[]): void {
    this.messages.update((m) => {
      const next = new Map(m);
      next.set(ticketId, msgs);
      return next;
    });
  }

  // State Signals
  readonly tickets = signal<Map<string, Called>>(new Map());
  readonly messages = signal<Map<string, CalledMessage[]>>(new Map());
  readonly selectedTicketId = signal<string | null>(null);
  readonly streamingMessages = signal<Map<string, StreamingState>>(new Map());
  readonly aiProcessingByTicket = signal<Map<string, AiProcessingState>>(new Map());

  readonly ticketListFilters = signal<{
    status?: 'open' | 'pending' | 'in_progress' | 'closed';
    search?: string;
  }>({});

  readonly countsBaseline = signal<CalledCounts>({
    all: 0,
    pending: 0,
    open: 0,
    closed: 0,
    in_progress: 0,
  });

  readonly countsDelta = signal<CalledCounts>({
    all: 0,
    pending: 0,
    open: 0,
    closed: 0,
    in_progress: 0,
  });

  // Derived (Computed) State
  readonly countsView = computed(() => {
    const base = this.countsBaseline();
    const delta = this.countsDelta();
    return {
      all: Math.max(0, base.all + delta.all),
      pending: Math.max(0, base.pending + delta.pending),
      open: Math.max(0, base.open + delta.open),
      closed: Math.max(0, base.closed + delta.closed),
      in_progress: Math.max(0, (base.in_progress ?? 0) + (delta.in_progress ?? 0)),
    };
  });

  readonly selectedTicket = computed(() => {
    const id = this.selectedTicketId();
    if (id === null) return null;
    return this.tickets().get(id) ?? null;
  });

  readonly selectedMessages = computed(() => {
    const id = this.selectedTicketId();
    if (id === null) return [];
    return this.messages().get(id) ?? [];
  });

  /**
   * Tickets sorted by most recent activity (descending).
   * Cached separately so filter changes don't trigger a re-sort.
   */
  readonly sortedTickets = computed(() => {
    const arr = Array.from(this.tickets().values());
    return arr.sort((a, b) => this.getTicketSortKey(b) - this.getTicketSortKey(a));
  });

  readonly filteredTickets = computed(() => {
    const tickets = this.sortedTickets();
    const filters = this.ticketListFilters();
    if (filters.status === undefined) return tickets;
    return tickets.filter((t) => t.status === filters.status);
  });

  readonly selectedTicketStreaming = computed(() => {
    const id = this.selectedTicketId();
    if (id === null) {
      return null;
    }

    return this.streamingMessages().get(id) ?? null;
  });

  readonly selectedTicketAiProcessing = computed(() => {
    const id = this.selectedTicketId();
    if (id === null) {
      return null;
    }

    return this.aiProcessingByTicket().get(id) ?? null;
  });

  // LRU Management setup
  private readonly maxTicketsInCache = 20;

  // Actions

  /**
   * Selects a ticket by ID, enforcing LRU cache management.
   *
   * @param id - The ticket ID to select, or null to deselect
   */
  selectTicket(id: string | null): void {
    this.selectedTicketId.set(id);
    if (id !== null) {
      this.enforceLRU(id);
    }
  }

  /**
   * Updates the ticket list filters.
   *
   * @param filters - Partial filters to merge with existing filters
   */
  setFilters(filters: Partial<ChatStoreState['ticketListFilters']>): void {
    this.ticketListFilters.update((prev) => ({ ...prev, ...filters }));
  }

  /**
   * Appends a message to a ticket's message list.
   *
   * @param ticketId - The ticket ID
   * @param message - The message to append
   */
  appendMessage(ticketId: string, message: CalledMessage): void {
    this.messages.update((m) => {
      const msgs = m.get(ticketId) ?? [];
      const next = this.mergeMessageCollections(msgs, [message]);
      const newMap = new Map(m);
      newMap.set(ticketId, next);
      return newMap;
    });
  }

  /**
   * Applies a batch of realtime events to the store state.
   *
   * @param events - Array of realtime adapter events to process
   */
  applyBatch(events: ChatRealtimeAdapterEvent[]): void {
    const changes = this.createBatchChanges();
    const flags: BatchFlags = {
      hasTicketChanges: false,
      hasMessageChanges: false,
      hasStreamingChanges: false,
      hasAiProcessingChanges: false,
      hasDeltaChanges: false,
    };

    for (const rawEvent of events) {
      this.applyBatchEvent(rawEvent, changes, flags);
    }

    // Apply batch updates inside a single set/update for each signal, minimising CD ticks
    if (flags.hasTicketChanges) this.tickets.set(changes.tickets);
    if (flags.hasMessageChanges) this.messages.set(changes.messages);
    if (flags.hasStreamingChanges) this.streamingMessages.set(changes.streaming);
    if (flags.hasAiProcessingChanges) this.aiProcessingByTicket.set(changes.aiProcessing);
    if (flags.hasDeltaChanges) this.countsDelta.set(changes.delta);

    // Call drift resyncs if needed
    changes.needsResync.forEach((ticketId) => {
      this.resyncTicket(ticketId);
    });
  }

  private createBatchChanges(): BatchChanges {
    return {
      tickets: new Map(untracked(this.tickets)),
      messages: new Map(untracked(this.messages)),
      streaming: new Map(untracked(this.streamingMessages)),
      aiProcessing: new Map(untracked(this.aiProcessingByTicket)),
      delta: { ...untracked(this.countsDelta) },
      needsResync: new Set<string>(),
    };
  }

  private applyBatchEvent(
    rawEvent: ChatRealtimeAdapterEvent,
    changes: BatchChanges,
    flags: BatchFlags,
  ): void {
    const type = rawEvent.type;
    const data = rawEvent.data as Record<string, unknown>;

    if (type === 'ticket.new') {
      const ticketData = rawEvent.data as TicketEventData;
      const tkt = ticketData.ticket;
      const ticketId = (tkt?.id != null ? String(tkt.id) : ticketData.ticket_id) as string | undefined;
      if (ticketId !== undefined && tkt !== undefined) {
        changes.tickets.set(ticketId, tkt);
        flags.hasTicketChanges = true;
        changes.delta.all += 1;
        const status = tkt.status as keyof ChatStoreState['countsDelta'] | undefined;
        // CalledCounts não possui index signature — cast via unknown para indexação dinâmica
        const deltaMap = changes.delta as unknown as Record<string, number | undefined>;
        if (status !== undefined && deltaMap[status] !== undefined) {
          deltaMap[status] = (deltaMap[status] ?? 0) + 1;
        }
        flags.hasDeltaChanges = true;
      }
      return;
    }

    if (type === 'chat.list.updated') {
      const ticketData = rawEvent.data as Record<string, unknown>;
      const ticketId = this.toStringValue(ticketData['id']);
      if (ticketId !== null) {
        const existing = changes.tickets.get(ticketId);
        changes.tickets.set(
          ticketId,
          existing !== undefined
            ? { ...existing, ...ticketData }
            : (ticketData as unknown as Called),
        );
        flags.hasTicketChanges = true;
      }
      return;
    }

    if (type === 'ticket.updated') {
      const ticketData = rawEvent.data as TicketEventData;
      const tkt = ticketData.ticket;
      const ticketId = (tkt?.id != null ? String(tkt.id) : ticketData.ticket_id) as string | undefined;
      if (ticketId !== undefined) {
        const existingTicket = changes.tickets.get(ticketId);
        if (existingTicket !== undefined && tkt !== undefined) {
          changes.tickets.set(ticketId, {
            ...existingTicket,
            ...tkt,
          });
          flags.hasTicketChanges = true;
        }
      }
      return;
    }

    if (type === 'msg.received' || type === 'msg.sent') {
      const msgData = rawEvent.data as MessageEventData;
      const ticketId = msgData.ticket_id ?? (data['ticket_id'] as string | undefined);
      if (ticketId !== undefined) {
        const msg: CalledMessage = msgData.message ?? msgData as CalledMessage;
        const existing = changes.messages.get(ticketId) ?? [];
        const merged = this.mergeMessageCollections(existing, [msg]);
        changes.messages.set(ticketId, merged);
        flags.hasMessageChanges = true;
      }
      return;
    }

    if (type === 'msg.edit' || type === 'chat.message.edit') {
      const ticketId = this.toStringValue(data['ticket_id']);
      const messageId = this.toStringValue(data['message_id']);

      if (ticketId !== null && messageId !== null) {
        const existing = changes.messages.get(ticketId) ?? [];
        const updated = this.applyEditToMessageCollection(
          existing,
          this.toChatMessageEditEvent(data, ticketId, messageId),
          rawEvent.timestamp,
        );

        changes.messages.set(ticketId, updated);
        flags.hasMessageChanges = true;
      }
      return;
    }

    if (type === 'msg.status') {
      const ticketId = this.toStringValue(data['ticket_id']);
      const messageId = this.toStringValue(data['message_id']);
      const externalId = this.toStringValue(data['external_id']);

      if (ticketId !== null && (messageId !== null || externalId !== null)) {
        const existing = changes.messages.get(ticketId) ?? [];
        const updated = existing.map((message) => {
          const matchesMessageId = messageId !== null && String(message.id) === messageId;
          const matchesExternalId =
            externalId !== null &&
            typeof message.external_id === 'string' &&
            message.external_id === externalId;

          if (!matchesMessageId && !matchesExternalId) {
            return message;
          }

          const incomingStatus = this.toStringValue(data['status']);
          const currentStatus = this.toStringValue(message.status);
          const status = this.resolveMessageStatus(currentStatus, incomingStatus);

          return {
            ...message,
            status,
            sent_at: this.toStringValue(data['sent_at']) ?? message.sent_at,
            delivered_at: this.toStringValue(data['delivered_at']) ?? message.delivered_at,
            read_at: this.toStringValue(data['read_at']) ?? message.read_at,
            file_url:
              Object.prototype.hasOwnProperty.call(data, 'file_url') &&
              (typeof data['file_url'] === 'string' || data['file_url'] === null)
                ? (data['file_url'] as string | null)
                : message.file_url,
            file_name:
              Object.prototype.hasOwnProperty.call(data, 'file_name') &&
              (typeof data['file_name'] === 'string' || data['file_name'] === null)
                ? (data['file_name'] as string | null)
                : message.file_name,
            mime_type:
              Object.prototype.hasOwnProperty.call(data, 'mime_type') &&
              (typeof data['mime_type'] === 'string' || data['mime_type'] === null)
                ? (data['mime_type'] as string | null)
                : message.mime_type,
            file_size:
              Object.prototype.hasOwnProperty.call(data, 'file_size') &&
              (typeof data['file_size'] === 'number' || data['file_size'] === null)
                ? (data['file_size'] as number | null)
                : message.file_size,
          };
        });

        changes.messages.set(ticketId, updated);
        flags.hasMessageChanges = true;
      }
      return;
    }

    if (type === 'ai.run.streaming') {
      const ticketId = this.toStringValue(data['ticket_id']);
      const runId = this.toStringValue(data['run_id']);
      if (ticketId !== null && runId !== null) {
        const chunk = this.toStringValue(data['chunk']) ?? '';
        const accumulatedText = this.toStringValue(data['accumulated_text']);
        const isFinal = data['is_final'] === true;
        const previous = changes.streaming.get(ticketId);

        const text =
          accumulatedText ?? (previous?.runId === runId ? `${previous.text}${chunk}` : chunk);

        changes.streaming.set(ticketId, {
          runId,
          text,
          isFinal,
          updatedAt: Date.now(),
        });

        flags.hasStreamingChanges = true;

        if (isFinal) {
          changes.streaming.delete(ticketId);
        }
      }
      return;
    }

    if (
      type === 'ai.processing.started' ||
      type === 'ai.processing.completed' ||
      type === 'ai.processing.failed' ||
      type === 'ai.processing.rejected'
    ) {
      const ticketId = this.toStringValue(data['ticket_id']);
      if (ticketId !== null) {
        const sourceStatus = this.toStringValue(data['status']) ?? '';
        const normalizedStatus =
          sourceStatus === 'queued'
            ? 'queued'
            : sourceStatus === 'completed'
              ? 'completed'
              : sourceStatus === 'failed'
                ? 'failed'
                : sourceStatus === 'rejected'
                  ? 'rejected'
                  : 'running';

        changes.aiProcessing.set(ticketId, {
          runId: this.toStringValue(data['run_id']),
          status: normalizedStatus,
          messageId: this.toStringValue(data['message_id']),
          error: this.toStringValue(data['error']) ?? this.toStringValue(data['reason']),
          updatedAt: rawEvent.timestamp,
        });
        flags.hasAiProcessingChanges = true;
      }
    }

    // Drift verification placeholder (could check 'event_version' field from data)
    if (data['event_version'] !== undefined) {
      // In full implementation we might verify monotonic increments and push to `needsResync` if drift is detected.
    }
  }

  /**
   * Resynchronizes a ticket's data from the server.
   *
   * @param ticketId - The ticket ID to resync
   */
  resyncTicket(ticketId: string): void {
    this.resyncSub?.unsubscribe();
    this.resyncSub = this.calledService.get(ticketId).subscribe((res) => {
      this.tickets.update((map) => {
        const newMap = new Map(map);
        newMap.set(ticketId, res.data);
        return newMap;
      });
    });

    const since = this.resolveSinceCursor(ticketId);
    this.resyncMessagesSub?.unsubscribe();
    this.resyncMessagesSub = this.fetchResyncMessages(ticketId, since).subscribe((incoming) => {
      if (incoming.length === 0) {
        return;
      }

      this.messages.update((messagesByTicket) => {
        const current = messagesByTicket.get(ticketId) ?? [];
        const merged = this.mergeMessageCollections(current, incoming);
        const next = new Map(messagesByTicket);
        next.set(ticketId, merged);
        return next;
      });
    });
  }

  private fetchResyncMessages(ticketId: string, since: string | null): Observable<CalledMessage[]> {
    const limit = 100;
    const buildOptions = (page: number): CalledMessageListOptions => ({
      page,
      limit,
      ...(since !== null ? { since } : {}),
    });

    return this.calledMessageService.list(ticketId, buildOptions(1)).pipe(
      expand((response) => {
        const messages = response.data.messages ?? [];
        const meta = response.data.meta;

        const currentPage = meta?.current_page ?? 1;
        const lastPage = meta?.last_page ?? currentPage;
        const hasMoreFromMeta = meta?.has_more === true || currentPage < lastPage;

        if (!hasMoreFromMeta || messages.length === 0) {
          return EMPTY;
        }

        return this.calledMessageService.list(ticketId, buildOptions(currentPage + 1));
      }),
      map((response) => response.data.messages ?? []),
      reduce(
        (allMessages, pageMessages) => allMessages.concat(pageMessages),
        [] as CalledMessage[],
      ),
      catchError(() => of([] as CalledMessage[])),
    );
  }

  /**
   * Resynchronizes the currently selected ticket upon websocket reconnect.
   */
  resyncSelectedTicketOnReconnect(): void {
    const ticketId = this.selectedTicketId();
    if (ticketId === null) {
      return;
    }

    this.resyncTicket(ticketId);
  }

  ngOnDestroy(): void {
    this.resyncSub?.unsubscribe();
    this.resyncMessagesSub?.unsubscribe();
  }

  private toStringValue(value: unknown): string | null {
    if (typeof value !== 'string') {
      return null;
    }

    const normalized = value.trim();
    return normalized === '' ? null : normalized;
  }

  private enforceLRU(accessedId: string): void {
    const map = untracked(this.tickets);
    if (map.size <= this.maxTicketsInCache) {
      return;
    }
    // Simple logic: If we have more than max size, we should remove the oldest.
    // In Map, iteration order is insertion order, but we should probably evict the unaccessed ones.
    // An LRU requires bringing accessed items to the end.
    const item = map.get(accessedId);
    if (item) {
      // Small trick to move it to the end of the Map
      this.tickets.update((m) => {
        const newMap = new Map(m);
        newMap.delete(accessedId);
        newMap.set(accessedId, item);

        while (newMap.size > this.maxTicketsInCache) {
          const oldestKey = newMap.keys().next().value as string | undefined;
          if (oldestKey !== undefined) {
            newMap.delete(oldestKey);
            // also remove messages
            this.messages.update((msgsMap) => {
              const mm = new Map(msgsMap);
              mm.delete(oldestKey);
              return mm;
            });
          }
        }
        return newMap;
      });
    }
  }

  private resolveSinceCursor(ticketId: string): string | null {
    const messages = this.messages().get(ticketId) ?? [];
    if (messages.length === 0) {
      return null;
    }

    // Usa o comparador estável (timestamp + id) para encontrar a mensagem mais recente,
    // garantindo desempate determinístico quando timestamps são iguais.
    const latest = messages.reduce((current, candidate) =>
      compareMessagesDesc(candidate, current) < 0 ? candidate : current,
    );

    if (typeof latest.id === 'string' && latest.id.trim() !== '') {
      return latest.id;
    }

    const createdAt = this.toStringValue(latest.created_at);
    return createdAt;
  }

  private mergeMessageCollections(
    current: CalledMessage[],
    incoming: CalledMessage[],
  ): CalledMessage[] {
    const messageById = new Map<string, CalledMessage>();

    for (const message of current) {
      const key = this.resolveMessageKey(message);
      if (key !== null) {
        messageById.set(key, message);
      }
    }

    for (const incomingMessage of incoming) {
      const key = this.resolveMessageKey(incomingMessage);
      if (key === null) {
        continue;
      }

      // When a complete message arrives with id=UUID and external_id=WA_MSG_ID,
      // remove any preliminary entry stored under id:${external_id} to prevent duplicates.
      if (
        typeof incomingMessage.external_id === 'string' &&
        incomingMessage.external_id.trim() !== ''
      ) {
        const staleKey = `id:${incomingMessage.external_id}`;
        messageById.delete(staleKey);
      }

      const previous = messageById.get(key);
      if (!previous) {
        messageById.set(key, incomingMessage);
        continue;
      }

      if (
        this.resolveMessageFreshnessTimestamp(incomingMessage) >=
        this.resolveMessageFreshnessTimestamp(previous)
      ) {
        messageById.set(key, { ...previous, ...incomingMessage });
      }
    }

    return Array.from(messageById.values()).sort(compareMessagesDesc);
  }

  private applyEditToMessageCollection(
    current: CalledMessage[],
    event: ChatMessageEditEvent,
    timestamp: number,
  ): CalledMessage[] {
    const editedAt = this.resolveEditedAt(event, timestamp);
    let hasChanges = false;

    const updated = current.map((message) => {
      let nextMessage = message;

      if (this.matchesMessageEdit(message, event)) {
        nextMessage = this.applyEditToMessage(message, event, editedAt);
      }

      const nextQuotedMessage = this.applyEditToQuotedMessage(
        nextMessage.quoted_message,
        event,
        editedAt,
      );
      if (nextQuotedMessage !== nextMessage.quoted_message) {
        nextMessage = {
          ...nextMessage,
          quoted_message: nextQuotedMessage,
        };
      }

      if (nextMessage !== message) {
        hasChanges = true;
      }

      return nextMessage;
    });

    return hasChanges ? updated : current;
  }

  private applyEditToMessage(
    message: CalledMessage,
    event: ChatMessageEditEvent,
    editedAt: string,
  ): CalledMessage {
    const previousContent = message.content ?? '';
    const nextContent = event.content;
    const previousHistory = message.edit_history ?? [];
    const lastHistoryEntry = previousHistory[previousHistory.length - 1];
    const contentChanged = previousContent !== nextContent;
    const shouldAppendHistory =
      contentChanged &&
      previousContent.trim() !== '' &&
      lastHistoryEntry?.content !== previousContent;

    const nextHistory = shouldAppendHistory
      ? [
          ...previousHistory,
          {
            content: previousContent,
            edited_at: editedAt,
          },
        ]
      : previousHistory;

    if (
      !contentChanged &&
      message.is_edited === event.is_edited &&
      (message.edited_at ?? null) === editedAt
    ) {
      return message;
    }

    return {
      ...message,
      content: nextContent,
      is_edited: event.is_edited,
      edited_at: editedAt,
      edit_history: nextHistory.length > 0 ? nextHistory : (message.edit_history ?? null),
    };
  }

  private applyEditToQuotedMessage(
    quotedMessage: CalledMessageQuotedMessage | null | undefined,
    event: ChatMessageEditEvent,
    editedAt: string,
  ): CalledMessageQuotedMessage | null | undefined {
    if (!quotedMessage || !this.matchesQuotedMessageEdit(quotedMessage, event)) {
      return quotedMessage;
    }

    if (
      quotedMessage.content === event.content &&
      quotedMessage.is_edited === event.is_edited &&
      (quotedMessage.edited_at ?? null) === editedAt
    ) {
      return quotedMessage;
    }

    return {
      ...quotedMessage,
      content: event.content,
      is_edited: event.is_edited,
      edited_at: editedAt,
    };
  }

  private matchesMessageEdit(message: CalledMessage, event: ChatMessageEditEvent): boolean {
    const matchesMessageId = String(message.id) === event.message_id;
    const matchesExternalId =
      typeof event.external_id === 'string' &&
      typeof message.external_id === 'string' &&
      message.external_id === event.external_id;

    return matchesMessageId || matchesExternalId;
  }

  private matchesQuotedMessageEdit(
    quotedMessage: CalledMessageQuotedMessage,
    event: ChatMessageEditEvent,
  ): boolean {
    const matchesMessageId =
      quotedMessage.id !== null &&
      quotedMessage.id !== undefined &&
      String(quotedMessage.id) === event.message_id;
    const matchesExternalId =
      typeof event.external_id === 'string' &&
      typeof quotedMessage.external_id === 'string' &&
      quotedMessage.external_id === event.external_id;

    return matchesMessageId || matchesExternalId;
  }

  private toChatMessageEditEvent(
    data: Record<string, unknown>,
    ticketId: string,
    messageId: string,
  ): ChatMessageEditEvent {
    return {
      ticket_id: ticketId,
      message_id: messageId,
      tenant_id: this.toStringValue(data['tenant_id']) ?? undefined,
      external_id: this.toStringValue(data['external_id']) ?? undefined,
      content: this.toStringValue(data['content']) ?? '',
      is_edited: data['is_edited'] === true,
      edited_at: this.toStringValue(data['edited_at']),
    };
  }

  private resolveEditedAt(event: ChatMessageEditEvent, fallbackTimestamp: number): string {
    const editedAt = this.toStringValue(event.edited_at);
    if (editedAt !== null) {
      return editedAt;
    }

    return new Date(fallbackTimestamp).toISOString();
  }

  private resolveMessageKey(message: CalledMessage): string | null {
    if (typeof message.id === 'string' && message.id.trim() !== '') {
      return `id:${message.id}`;
    }

    if (typeof message.id === 'number') {
      return `id:${String(message.id)}`;
    }

    if (typeof message.external_id === 'string' && message.external_id.trim() !== '') {
      return `external:${message.external_id}`;
    }

    return null;
  }

  private resolveMessageFreshnessTimestamp(message: CalledMessage): number {
    const candidates = [
      message.edited_at,
      message.read_at,
      message.delivered_at,
      message.sent_at,
      message.created_at,
      message.deleted_at,
    ];

    for (const value of candidates) {
      if (typeof value !== 'string' || value.trim() === '') {
        continue;
      }

      const timestamp = Date.parse(value);
      if (!Number.isNaN(timestamp)) {
        return timestamp;
      }
    }

    return 0;
  }

  private resolveMessageOrderTimestamp(message: CalledMessage): number {
    const timestamp = resolveStableOrderTimestamp(message);
    if (timestamp !== 0) {
      return timestamp;
    }
    return this.resolveMessageFreshnessTimestamp(message);
  }

  private resolveMessageStatus(current: string | null, incoming: string | null): string | null {
    if (incoming === null || incoming === '') {
      return current;
    }

    if (current === null || current === '') {
      return incoming;
    }

    const currentRank = ChatStore.MESSAGE_STATUS_RANK[current] ?? -1;
    const incomingRank = ChatStore.MESSAGE_STATUS_RANK[incoming] ?? -1;
    return incomingRank >= currentRank ? incoming : current;
  }

  /**
   * Returns the numeric sort key for a ticket (descending = most recent first).
   * Uses Date.parse() for efficiency over new Date().getTime().
   */
  private getTicketSortKey(ticket: Called): number {
    return Date.parse(ticket.last_message?.created_at ?? ticket.created_at ?? '') || 0;
  }

  /**
   * Binary-inserts a ticket into a descending-sorted array without full re-sort.
   * Returns a new array with the ticket at the correct position.
   */
  binaryInsertTicket(sorted: Called[], ticket: Called): Called[] {
    const key = this.getTicketSortKey(ticket);
    let low = 0;
    let high = sorted.length;
    while (low < high) {
      const mid = (low + high) >>> 1;
      if (this.getTicketSortKey(sorted[mid]) > key) {
        low = mid + 1;
      } else {
        high = mid;
      }
    }
    const result = sorted.slice();
    result.splice(low, 0, ticket);
    return result;
  }
}
