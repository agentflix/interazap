import { DestroyRef, effect, inject, Injectable, signal, untracked, computed } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { type Subscription } from 'rxjs';
import { AuthStoreService } from 'src/app/core/services/auth-store.service';
import {
  type CalledMessage,
  type CalledMessageListResponse,
  CalledMessageService,
} from 'src/app/core/services/called-message.service';
import { CalledService } from 'src/app/core/services/called.service';
import { ChatMessageCacheService } from 'src/app/core/services/chat-message-cache.service';
import { ChatRealtimeService } from 'src/app/core/services/chat-realtime.service';
import { ChatRefreshService } from 'src/app/core/services/chat-refresh.service';
import { ChatStartService } from 'src/app/core/services/chat-start.service';
import { mergeAndSortMessagesAsc } from 'src/app/core/utils/message-comparator.util';

@Injectable()
export class UserChatThreadStore {
  private readonly destroyRef = inject(DestroyRef);
  private readonly messageService = inject(CalledMessageService);
  private readonly calledService = inject(CalledService);
  private readonly chatRefresh = inject(ChatRefreshService);
  private readonly authStore = inject(AuthStoreService);
  private readonly chatStart = inject(ChatStartService);
  private readonly realtime = inject(ChatRealtimeService);
  private readonly cacheService = inject(ChatMessageCacheService);

  readonly calledId = signal<string | null>(null);
  readonly isLoading = signal(false);
  readonly isLoadingMore = signal(false);
  readonly hasMore = signal(true);
  readonly loadError = signal<string | null>(null);
  readonly contactTyping = signal<{ userId?: string; contactId?: string } | null>(null);

  private readonly page = signal(1);
  private readonly limit = signal(30);

  readonly messages = computed<CalledMessage[]>(() => {
    const id = this.calledId();
    if (id === null || id === '') return [];
    return this.cacheService.getOrCreate(id)();
  });

  readonly hasMessages = computed(() => this.messages().length > 0);

  private loadSub: Subscription | null = null;
  private markAsReadSub: Subscription | null = null;
  private markAsReadTimer: ReturnType<typeof setTimeout> | null = null;
  private hasLoadedCurrentTicket = false;
  private initialLoadDone = false;
  private readonly realtimeBuffer: CalledMessage[] = [];

  constructor() {
    this.setupRealtimeEffects();
  }

  connectRealtime(): void {
    const tenantId = this.authStore.user()?.tenant_id;
    if (tenantId !== null && tenantId !== undefined) {
      this.realtime.connect(String(tenantId));
    }
  }

  openStartConversation(): void {
    this.chatStart.open();
  }

  setContext(ticketId: string | null | undefined, canLoadMessages: boolean): void {
    const normalizedId = ticketId ?? null;
    const previousId = this.calledId();

    if (previousId !== normalizedId) {
      if (previousId !== null && previousId !== '') {
        this.realtime.leaveTicket(previousId);
      }
      this.calledId.set(normalizedId);
      this.resetState();
    }

    if (normalizedId === null || normalizedId === '' || !canLoadMessages) {
      return;
    }

    if (!this.hasLoadedCurrentTicket) {
      this.startTicketLoading(normalizedId);
    }
  }

  retryLoadMessages(): void {
    if (!this.calledId()) {
      return;
    }

    this.page.set(1);
    this.hasMore.set(true);
    this.loadMessages(false);
  }

  loadMore(): boolean {
    if (!this.calledId() || this.isLoading() || this.isLoadingMore() || !this.hasMore()) {
      return false;
    }

    this.isLoadingMore.set(true);
    this.page.update((value) => value + 1);
    this.loadMessages(true);
    return true;
  }

  appendMessage(message: CalledMessage): void {
    const ticketId = this.calledId();
    if (!ticketId) {
      return;
    }

    const nextMessages = this.mergeAndSort(this.messages(), [message]);
    this.cacheService.setAll(ticketId, nextMessages);
  }

  replaceMessage(tempId: string, message: CalledMessage): void {
    const ticketId = this.calledId();
    if (!ticketId) {
      return;
    }

    this.cacheService.replace(ticketId, tempId, message);
  }

  private startTicketLoading(ticketId: string): void {
    this.hasLoadedCurrentTicket = true;
    this.realtime.joinTicket(ticketId);
    this.loadMessages(false);
    this.scheduleMarkAsRead(ticketId);
  }

  private scheduleMarkAsRead(ticketId: string): void {
    if (this.markAsReadTimer !== null) {
      clearTimeout(this.markAsReadTimer);
    }

    this.markAsReadTimer = setTimeout(() => {
      this.markAsReadSub?.unsubscribe();
      this.markAsReadSub = this.calledService
        .markAsRead(ticketId)
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe({
          next: () => this.chatRefresh.markAsRead(ticketId),
          error: () => {},
        });
      this.markAsReadTimer = null;
    }, 2000);
  }

  private loadMessages(append: boolean): void {
    const ticketId = this.calledId();
    if (ticketId === null || ticketId === '') return;

    if (!append) {
      this.isLoading.set(true);
    }
    this.loadError.set(null);

    this.loadSub?.unsubscribe();
    this.loadSub = this.messageService
      .list(ticketId, { page: this.page(), limit: this.limit() })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => this.handleLoadSuccess(response, ticketId, append),
        error: () => this.handleLoadError(ticketId, append),
      });
  }

  private handleLoadSuccess(
    response: CalledMessageListResponse,
    ticketId: string,
    append: boolean,
  ): void {
    if (this.calledId() !== ticketId) {
      return;
    }

    const payload = response.data;
    const incomingMessages = payload.messages ?? [];
    const mergedIncoming = this.flushRealtimeBuffer(incomingMessages);
    const currentMessages = append ? this.cacheService.getOrCreate(ticketId)() : [];

    this.cacheService.setAll(ticketId, this.mergeAndSort(currentMessages, mergedIncoming));

    this.hasMore.set(payload.meta?.has_more ?? incomingMessages.length >= this.limit());
    this.loadError.set(null);
    this.isLoading.set(false);
    this.isLoadingMore.set(false);
    this.initialLoadDone = true;
  }

  private handleLoadError(ticketId: string, append: boolean): void {
    if (this.calledId() !== ticketId) {
      return;
    }

    if (append) {
      this.page.update((value) => Math.max(1, value - 1));
    }

    this.loadError.set('Nao foi possivel carregar as mensagens.');
    this.isLoading.set(false);
    this.isLoadingMore.set(false);
    this.initialLoadDone = true;
    this.realtimeBuffer.length = 0;
  }

  private flushRealtimeBuffer(apiMessages: CalledMessage[]): CalledMessage[] {
    if (this.realtimeBuffer.length === 0) {
      return apiMessages;
    }

    const buffered = [...this.realtimeBuffer];
    this.realtimeBuffer.length = 0;
    return [...apiMessages, ...buffered];
  }

  private mergeAndSort(current: CalledMessage[], incoming: CalledMessage[]): CalledMessage[] {
    return mergeAndSortMessagesAsc(current, incoming);
  }

  private setupRealtimeEffects(): void {
    effect(() => {
      const { event, version } = this.realtime.messageStatus();
      if (!event || version < 1 || !this.initialLoadDone) return;

      const currentId = this.calledId();
      // ticket_id is optional in provider webhook events (delivered/read receipts
      // from WhatsApp etc. are emitted without ticket_id by the gateway).
      // When omitted, allow the update and let updateStatus() scope it by message_id/external_id.
      if (
        !currentId ||
        (event.ticket_id !== undefined &&
          event.ticket_id !== null &&
          String(event.ticket_id) !== currentId)
      )
        return;

      untracked(() => {
        this.cacheService.updateStatus(currentId, event);
      });
    });

    effect(() => {
      const { event, version } = this.realtime.newMessage();
      if (!event || version < 1) return;

      untracked(() => this.handleRealtimeNewMessage(event));
    });

    effect(() => {
      const { event, version } = this.realtime.typing();
      if (!event || version < 1) return;

      const currentId = this.calledId();
      if (!currentId || event.ticket_id !== currentId) return;

      this.contactTyping.set(
        event.is_typing
          ? {
              userId: event.user_id,
              contactId: event.contact_id,
            }
          : null,
      );
    });

    effect(() => {
      const { event, version } = this.realtime.delete();
      if (!event || version < 1 || !this.initialLoadDone) return;

      const currentId = this.calledId();
      if (!currentId || event.ticket_id !== currentId) return;

      this.updateMessageById(event.message_id, (message) => ({
        ...message,
        status: 'deleted',
        is_deleted: true,
        deleted_at: event.deleted_at ?? message.deleted_at,
      }));
    });

    effect(() => {
      const { event, version } = this.realtime.edit();
      if (!event || version < 1 || !this.initialLoadDone) return;

      const currentId = this.calledId();
      if (!currentId || event.ticket_id !== currentId) return;

      this.updateMessageById(event.message_id, (message) => ({
        ...message,
        content: event.content,
        is_edited: event.is_edited,
        edited_at: event.edited_at ?? message.edited_at,
      }));
    });

    effect(() => {
      const { event, version } = this.realtime.reaction();
      if (!event || version < 1 || !this.initialLoadDone) return;

      const currentId = this.calledId();
      if (!currentId || event.ticket_id !== currentId) return;

      this.updateMessageById(event.message_id, (message) => ({
        ...message,
        reactions: event.reactions ?? message.reactions,
      }));
    });
  }

  private handleRealtimeNewMessage(event: {
    ticket_id: string;
    message: {
      id: string;
      content?: string;
      type?: string;
      direction?: string;
      status?: string;
      created_at?: string;
      file_url?: string;
      file_name?: string;
      mime_type?: string;
      file_size?: number;
    };
  }): void {
    const currentId = this.calledId();
    if (!currentId || event.ticket_id !== currentId) {
      return;
    }

    const message: CalledMessage = {
      id: event.message.id,
      content: event.message.content ?? null,
      type: event.message.type,
      direction: event.message.direction === 'outgoing' ? 'outgoing' : 'incoming',
      status: event.message.status ?? null,
      created_at: event.message.created_at,
      ticket_id: currentId,
      file_url: event.message.file_url ?? null,
      file_name: event.message.file_name ?? null,
      mime_type: event.message.mime_type ?? null,
      file_size: event.message.file_size ?? null,
    };

    if (!this.initialLoadDone) {
      const alreadyBuffered = this.realtimeBuffer.some(
        (item) => String(item.id) === String(message.id),
      );
      if (!alreadyBuffered) {
        this.realtimeBuffer.push(message);
      }
      return;
    }

    const exists = this.messages().some((item) => String(item.id) === String(message.id));
    if (exists) {
      return;
    }

    const nextMessages = this.mergeAndSort(this.messages(), [message]);
    this.cacheService.setAll(currentId, nextMessages);
  }

  private updateMessageById(
    messageId: string,
    mapFn: (message: CalledMessage) => CalledMessage,
  ): void {
    const ticketId = this.calledId();
    if (!ticketId) return;

    const currentMessages = this.cacheService.getOrCreate(ticketId)();
    const nextMessages = currentMessages.map((message) => {
      if (String(message.id) !== String(messageId)) {
        return message;
      }

      return mapFn(message);
    });

    this.cacheService.setAll(ticketId, nextMessages);
  }

  private resetState(): void {
    this.loadSub?.unsubscribe();
    this.loadSub = null;

    this.markAsReadSub?.unsubscribe();
    this.markAsReadSub = null;

    if (this.markAsReadTimer !== null) {
      clearTimeout(this.markAsReadTimer);
      this.markAsReadTimer = null;
    }

    this.hasLoadedCurrentTicket = false;
    this.initialLoadDone = false;
    this.realtimeBuffer.length = 0;
    this.page.set(1);
    this.hasMore.set(true);
    this.isLoading.set(false);
    this.isLoadingMore.set(false);
    this.loadError.set(null);
    this.contactTyping.set(null);
  }
}
