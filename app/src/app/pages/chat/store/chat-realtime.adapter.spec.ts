import { TestBed } from '@angular/core/testing';
import { ChatRealtimeAdapter } from './chat-realtime.adapter';
import { ChatRealtimeService } from '@core/services/chat-realtime.service';
import { ChatRewriteRolloutService } from '@core/services/chat-rewrite-rollout.service';
import { ChatStore, type ChatRealtimeAdapterEvent } from './chat.store';
import { signal } from '@angular/core';
import { vi } from 'vitest';

describe('ChatRealtimeAdapter', () => {
  let adapter: ChatRealtimeAdapter;
  let chatStoreMock: {
    applyBatch: ReturnType<typeof vi.fn>;
    resyncSelectedTicketOnReconnect: ReturnType<typeof vi.fn>;
  };
  let activitySignal: ReturnType<typeof signal>;
  let editSignal: ReturnType<typeof signal>;
  let typingSignal: ReturnType<typeof signal>;
  let connectedSignal: ReturnType<typeof signal>;

  beforeEach(() => {
    vi.useFakeTimers();

    chatStoreMock = {
      applyBatch: vi.fn(),
      resyncSelectedTicketOnReconnect: vi.fn(),
    };

    activitySignal = signal({ event: null, version: 0 });
    editSignal = signal({ event: null, version: 0 });
    typingSignal = signal({ event: null, version: 0 });
    connectedSignal = signal(false);

    const realtimeServiceMock = {
      activity: activitySignal,
      edit: editSignal,
      typing: typingSignal,
      connected: connectedSignal,
    };

    TestBed.configureTestingModule({
      providers: [
        ChatRealtimeAdapter,
        { provide: ChatStore, useValue: chatStoreMock },
        {
          provide: ChatRewriteRolloutService,
          useValue: { isEnabledForCurrentUser: () => true },
        },
        { provide: ChatRealtimeService, useValue: realtimeServiceMock },
      ],
    });
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  describe('Event Batching', () => {
    it('should buffer multiple events into a single batch applied to the store', () => {
      adapter = TestBed.inject(ChatRealtimeAdapter);
      expect(adapter).toBeDefined();

      // We simulate multiple events firing sequentially
      activitySignal.set({
        event: {
          subevents: [
            { type: 'msg.received', data: { message_id: 'm1', ticket_id: 't1' } },
            { type: 'msg.received', data: { message_id: 'm2', ticket_id: 't1' } },
          ],
        },
        version: 1,
      });

      TestBed.flushEffects();

      // No call before buffer time
      expect(chatStoreMock.applyBatch).not.toHaveBeenCalled();

      // Advance buffer time
      vi.advanceTimersByTime(101);

      expect(chatStoreMock.applyBatch).toHaveBeenCalledTimes(1);

      const batch = chatStoreMock.applyBatch.mock.calls[0][0] as ChatRealtimeAdapterEvent[];
      expect(batch.length).toBe(2);
      expect(batch[0].type).toBe('msg.received');
    });
  });

  describe('Reconnect Resync', () => {
    it('triggers deterministic selected-ticket resync only on reconnect transitions', () => {
      adapter = TestBed.inject(ChatRealtimeAdapter);
      expect(adapter).toBeDefined();
      expect(chatStoreMock.resyncSelectedTicketOnReconnect).not.toHaveBeenCalled();

      connectedSignal.set(true);
      TestBed.flushEffects();

      expect(chatStoreMock.resyncSelectedTicketOnReconnect).not.toHaveBeenCalled();

      connectedSignal.set(false);
      TestBed.flushEffects();
      connectedSignal.set(true);
      TestBed.flushEffects();

      expect(chatStoreMock.resyncSelectedTicketOnReconnect).toHaveBeenCalledTimes(1);
    });
  });

  describe('Deduplication', () => {
    it('should deduplicate events with the same specific key within the window', () => {
      adapter = TestBed.inject(ChatRealtimeAdapter);

      activitySignal.set({
        event: {
          subevents: [
            { type: 'msg.status', data: { message_id: 'm1', status: 'sent' } },
            { type: 'msg.status', data: { message_id: 'm1', status: 'delivered' } }, // same message_id
            { type: 'ticket.updated', data: { ticket_id: 't1', title: 'Test 1' } }, // Should keep both of these
            { type: 'ticket.updated', data: { ticket_id: 't2', title: 'Test 2' } }, // as they are on different entities
            { type: 'ticket.updated', data: { ticket_id: 't1', title: 'Test Final' } },
          ],
        },
        version: 1,
      });

      TestBed.flushEffects();
      vi.advanceTimersByTime(101);

      expect(chatStoreMock.applyBatch).toHaveBeenCalledTimes(1);

      const batch = chatStoreMock.applyBatch.mock.calls[0][0] as ChatRealtimeAdapterEvent[];
      // We expect only 1 msg.status for m1 (the latest), and 2 ticket.updated (one for t1, one for t2)
      expect(batch.length).toBe(3);

      const msgStatus = batch.filter((b) => b.type === 'msg.status');
      expect(msgStatus.length).toBe(1);
      expect((msgStatus[0].data as { status: string }).status).toBe('delivered'); // Keeps last event

      const ticketUpdates = batch.filter((b) => b.type === 'ticket.updated');
      expect(ticketUpdates.length).toBe(2);
    });

    it('normalizes direct chat.message.edit events into msg.edit batches', () => {
      adapter = TestBed.inject(ChatRealtimeAdapter);

      editSignal.set({
        event: {
          message_id: 'm1',
          ticket_id: 't1',
          content: 'mensagem editada',
          is_edited: true,
        },
        version: 1,
      });

      TestBed.flushEffects();
      vi.advanceTimersByTime(101);

      expect(chatStoreMock.applyBatch).toHaveBeenCalledTimes(1);

      const batch = chatStoreMock.applyBatch.mock.calls[0][0] as ChatRealtimeAdapterEvent[];
      expect(batch).toHaveLength(1);
      expect(batch[0].type).toBe('msg.edit');
      expect(batch[0].data).toEqual({
        message_id: 'm1',
        ticket_id: 't1',
        content: 'mensagem editada',
        is_edited: true,
      });
    });

    it('does not collapse invalid msg.edit without ticket_id with a valid event of same message', () => {
      adapter = TestBed.inject(ChatRealtimeAdapter);

      activitySignal.set({
        event: {
          subevents: [
            {
              type: 'msg.edit',
              data: {
                message_id: 'm1',
                content: 'evento inválido sem ticket',
                is_edited: true,
              },
            },
            {
              type: 'msg.edit',
              data: {
                message_id: 'm1',
                ticket_id: 't1',
                content: 'evento válido com ticket',
                is_edited: true,
              },
            },
          ],
        },
        version: 1,
      });

      TestBed.flushEffects();
      vi.advanceTimersByTime(101);

      expect(chatStoreMock.applyBatch).toHaveBeenCalledTimes(1);

      const batch = chatStoreMock.applyBatch.mock.calls[0][0] as ChatRealtimeAdapterEvent[];
      expect(batch).toHaveLength(2);
      expect(batch[0].type).toBe('msg.edit');
      expect(batch[1].type).toBe('msg.edit');
      expect((batch[0].data as { ticket_id?: string }).ticket_id).toBeUndefined();
      expect((batch[1].data as { ticket_id?: string }).ticket_id).toBe('t1');
    });

    it('does not collapse sequential valid msg.edit events when edited_at changes', () => {
      adapter = TestBed.inject(ChatRealtimeAdapter);

      activitySignal.set({
        event: {
          subevents: [
            {
              type: 'msg.edit',
              data: {
                message_id: 'm1',
                ticket_id: 't1',
                content: 'texto v1',
                is_edited: true,
                edited_at: '2026-03-17T10:00:00.000Z',
              },
            },
            {
              type: 'msg.edit',
              data: {
                message_id: 'm1',
                ticket_id: 't1',
                content: 'texto v2',
                is_edited: true,
                edited_at: '2026-03-17T10:00:00.010Z',
              },
            },
          ],
        },
        version: 1,
      });

      TestBed.flushEffects();
      vi.advanceTimersByTime(101);

      expect(chatStoreMock.applyBatch).toHaveBeenCalledTimes(1);

      const batch = chatStoreMock.applyBatch.mock.calls[0][0] as ChatRealtimeAdapterEvent[];
      expect(batch).toHaveLength(2);
      expect(batch[0].type).toBe('msg.edit');
      expect(batch[1].type).toBe('msg.edit');
      expect((batch[0].data as { edited_at?: string }).edited_at).toBe('2026-03-17T10:00:00.000Z');
      expect((batch[1].data as { edited_at?: string }).edited_at).toBe('2026-03-17T10:00:00.010Z');
    });
  });
});
