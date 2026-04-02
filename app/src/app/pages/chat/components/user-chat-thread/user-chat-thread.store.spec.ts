import { TestBed } from '@angular/core/testing';
import { signal } from '@angular/core';
import { of, throwError } from 'rxjs';
import { describe, expect, it, beforeEach, vi } from 'vitest';
import {
  type CalledMessage,
  type CalledMessageListResponse,
  CalledMessageService,
} from 'src/app/core/services/called-message.service';
import { AuthStoreService } from 'src/app/core/services/auth-store.service';
import { CalledService } from 'src/app/core/services/called.service';
import { ChatMessageCacheService } from 'src/app/core/services/chat-message-cache.service';
import { ChatRefreshService } from 'src/app/core/services/chat-refresh.service';
import { ChatRealtimeService } from 'src/app/core/services/chat-realtime.service';
import { ChatStartService } from 'src/app/core/services/chat-start.service';
import {
  type ChatMessageDeleteEvent,
  type ChatMessageEditEvent,
  type ChatMessageReactionEvent,
  type ChatMessageStatusEvent,
  type ChatNewMessageEvent,
  type ChatTypingEvent,
} from 'src/app/core/services/chat-realtime.events';
import { UserChatThreadStore } from './user-chat-thread.store';

interface RealtimeState<T> {
  event: T | null;
  version: number;
}

describe('UserChatThreadStore', () => {
  let store: UserChatThreadStore;

  const messageService = {
    list: vi.fn(),
  };

  const calledService = {
    markAsRead: vi.fn().mockReturnValue(of(undefined)),
  };

  const chatRefresh = {
    markAsRead: vi.fn(),
  };

  const authStore = {
    user: signal({ id: 'u-1', name: 'User', email: 'u@x.com', tenant_id: 't-1', permissions: [] }),
  };

  const chatStart = {
    open: vi.fn(),
  };

  const realtimeMessageStatus = signal<RealtimeState<ChatMessageStatusEvent>>({
    event: null,
    version: 0,
  });
  const realtimeNewMessage = signal<RealtimeState<ChatNewMessageEvent>>({
    event: null,
    version: 0,
  });
  const realtimeTyping = signal<RealtimeState<ChatTypingEvent>>({
    event: null,
    version: 0,
  });
  const realtimeDelete = signal<RealtimeState<ChatMessageDeleteEvent>>({
    event: null,
    version: 0,
  });
  const realtimeEdit = signal<RealtimeState<ChatMessageEditEvent>>({
    event: null,
    version: 0,
  });
  const realtimeReaction = signal<RealtimeState<ChatMessageReactionEvent>>({
    event: null,
    version: 0,
  });

  const realtime = {
    messageStatus: realtimeMessageStatus,
    newMessage: realtimeNewMessage,
    typing: realtimeTyping,
    delete: realtimeDelete,
    edit: realtimeEdit,
    reaction: realtimeReaction,
    connect: vi.fn(),
    joinTicket: vi.fn(),
    leaveTicket: vi.fn(),
  };

  beforeEach(() => {
    vi.clearAllMocks();

    TestBed.configureTestingModule({
      providers: [
        UserChatThreadStore,
        ChatMessageCacheService,
        { provide: CalledMessageService, useValue: messageService },
        { provide: CalledService, useValue: calledService },
        { provide: ChatRefreshService, useValue: chatRefresh },
        { provide: AuthStoreService, useValue: authStore },
        { provide: ChatStartService, useValue: chatStart },
        { provide: ChatRealtimeService, useValue: realtime },
      ],
    });

    store = TestBed.inject(UserChatThreadStore);
  });

  it('deve carregar mensagens ao definir contexto com ticket', () => {
    const response: CalledMessageListResponse = {
      data: {
        messages: [{ id: 'm-1', content: 'hello', direction: 'incoming' } as CalledMessage],
        meta: {
          current_page: 1,
          has_more: false,
          last_page: 1,
          per_page: 30,
          total: 1,
        },
      },
    };

    messageService.list.mockReturnValue(of(response));

    store.setContext('ticket-1', true);

    expect(messageService.list).toHaveBeenCalled();
    expect(store.calledId()).toBe('ticket-1');
    expect(store.messages().length).toBe(1);
    expect(realtime.joinTicket).toHaveBeenCalledWith('ticket-1');
  });

  it('deve manter estado de erro e permitir retry', () => {
    messageService.list.mockReturnValueOnce(throwError(() => new Error('fail')));
    store.setContext('ticket-2', true);

    expect(store.loadError()).toBe('Nao foi possivel carregar as mensagens.');

    const response: CalledMessageListResponse = {
      data: {
        messages: [{ id: 'm-2', content: 'ok', direction: 'incoming' } as CalledMessage],
        meta: {
          current_page: 1,
          has_more: false,
          last_page: 1,
          per_page: 30,
          total: 1,
        },
      },
    };

    messageService.list.mockReturnValueOnce(of(response));
    store.retryLoadMessages();

    expect(store.loadError()).toBeNull();
    expect(store.messages().map((message) => String(message.id))).toContain('m-2');
  });

  it('deve delegar abertura de conversa para o service de start', () => {
    store.openStartConversation();

    expect(chatStart.open).toHaveBeenCalledTimes(1);
  });
});
