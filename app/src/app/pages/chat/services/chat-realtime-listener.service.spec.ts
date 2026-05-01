import type { DestroyRef } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { Subject } from 'rxjs';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { ChatRefreshService } from 'src/app/core/services/chat-refresh.service';
import { RealtimeService } from 'src/app/core/services/realtime.service';
import { ChatRealtimeListenerService } from './chat-realtime-listener.service';

vi.mock('src/app/shared/utils/notifications/chat-audio', () => ({
  tocarNotificacao: vi.fn().mockResolvedValue(undefined),
}));

class RealtimeServiceStub {
  connect = vi.fn();
  message$ = new Subject<unknown>();
  activity$ = new Subject<unknown>();
  newTicket$ = new Subject<unknown>();
  on = vi.fn((eventName: string) => {
    if (eventName === 'message.received') return this.message$.asObservable();
    if (eventName === 'chat.activity') return this.activity$.asObservable();
    if (eventName === 'chat.ticket.new') return this.newTicket$.asObservable();
    return new Subject().asObservable();
  });
}

class ChatRefreshServiceStub {
  request = vi.fn();
}

interface FakeDestroyRef extends DestroyRef {
  destroy(): void;
}

function createDestroyRef(): FakeDestroyRef {
  const callbacks: (() => void)[] = [];
  return {
    onDestroy: (cb: () => void) => {
      callbacks.push(cb);
      return () => {
        const i = callbacks.indexOf(cb);
        if (i >= 0) callbacks.splice(i, 1);
      };
    },
    destroy: () => {
      while (callbacks.length) callbacks.shift()?.();
    },
  } as FakeDestroyRef;
}

describe('ChatRealtimeListenerService', () => {
  let service: ChatRealtimeListenerService;
  let realtime: RealtimeServiceStub;
  let refresh: ChatRefreshServiceStub;
  let destroyRef: FakeDestroyRef;

  beforeEach(() => {
    realtime = new RealtimeServiceStub();
    refresh = new ChatRefreshServiceStub();
    TestBed.configureTestingModule({
      providers: [
        ChatRealtimeListenerService,
        { provide: RealtimeService, useValue: realtime },
        { provide: ChatRefreshService, useValue: refresh },
      ],
    });
    service = TestBed.inject(ChatRealtimeListenerService);
    destroyRef = createDestroyRef();
  });

  it('connects to realtime and subscribes once per start call', () => {
    service.start(destroyRef);
    expect(realtime.connect).toHaveBeenCalledTimes(1);
    expect(realtime.on).toHaveBeenCalledWith('message.received');
    expect(realtime.on).toHaveBeenCalledWith('chat.activity');
    expect(realtime.on).toHaveBeenCalledWith('chat.ticket.new');
  });

  it('re-subscribes after the previous DestroyRef is destroyed (remount)', () => {
    service.start(destroyRef);
    const initialOnCalls = realtime.on.mock.calls.length;

    destroyRef.destroy();

    const nextDestroyRef = createDestroyRef();
    service.start(nextDestroyRef);

    expect(realtime.on.mock.calls.length).toBe(initialOnCalls * 2);
    realtime.activity$.next({ subevents: [{ type: 'ticket.new' }] });
    expect(refresh.request).toHaveBeenCalled();
  });

  it('requests refresh on ticket.new subevent in chat.activity', () => {
    service.start(destroyRef);
    realtime.activity$.next({ subevents: [{ type: 'ticket.new' }] });
    expect(refresh.request).toHaveBeenCalled();
  });

  it('ignores non-mutating activity subevents', () => {
    service.start(destroyRef);
    realtime.activity$.next({ subevents: [{ type: 'irrelevant' }] });
    expect(refresh.request).not.toHaveBeenCalled();
  });

  it('ignores chat.ticket.new without ticket id', () => {
    service.start(destroyRef);
    realtime.newTicket$.next({});
    expect(refresh.request).not.toHaveBeenCalled();
  });

  it('requests refresh on chat.ticket.new with id', () => {
    service.start(destroyRef);
    realtime.newTicket$.next({ ticket_id: 'abc' });
    expect(refresh.request).toHaveBeenCalled();
  });
});
