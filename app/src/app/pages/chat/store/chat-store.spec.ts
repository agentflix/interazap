import { TestBed } from '@angular/core/testing';
import { ChatStore } from './chat.store';
import { CalledService, type Called } from '@core/services/called.service';
import { CalledMessageService, type CalledMessage } from '@core/services/called-message.service';
import { HttpClientTestingModule } from '@angular/common/http/testing';
import { of } from 'rxjs';

describe('ChatStore', () => {
  let store: ChatStore;
  let calledServiceMock: {
    counts: ReturnType<typeof vi.fn>;
    get: ReturnType<typeof vi.fn>;
  };
  let calledMessageServiceMock: {
    list: ReturnType<typeof vi.fn>;
  };

  beforeEach(() => {
    calledServiceMock = {
      counts: vi
        .fn()
        .mockReturnValue(of({ data: { all: 10, open: 5, closed: 5, pending: 0, in_progress: 0 } })),
      get: vi.fn().mockReturnValue(of({ data: { called: { id: 't1', status: 'open' } } })),
    };

    calledMessageServiceMock = {
      list: vi.fn().mockReturnValue(of({ data: { messages: [{ id: 'm1', content: 'test' }] } })),
    };

    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [
        { provide: CalledService, useValue: calledServiceMock },
        { provide: CalledMessageService, useValue: calledMessageServiceMock },
        ChatStore,
      ],
    });

    store = TestBed.inject(ChatStore);
  });

  it('should be created', () => {
    expect(store).toBeTruthy();
  });

  describe('selectTicket', () => {
    it('should set selectedTicketId', () => {
      store.selectTicket('t1');
      expect(store.selectedTicketId()).toBe('t1');
    });

    it('should not auto-load messages when selecting a ticket', () => {
      store.selectTicket('t1');
      expect(calledMessageServiceMock.list).not.toHaveBeenCalled();
      expect(store.messages().get('t1')).toBeUndefined();
    });
  });

  describe('counts', () => {
    it('should initialize counts via Resource and compute correctly with delta', async () => {
      // Need to wait for rxResource to resolve
      await new Promise((resolve) => setTimeout(resolve, 0));
      store.countsBaseline.set({ all: 10, open: 5, pending: 0, closed: 5, in_progress: 0 });
      store.countsDelta.set({ all: 1, open: 1, pending: 0, closed: 0, in_progress: 0 });

      expect(store.countsView()).toEqual({
        all: 11,
        open: 6,
        pending: 0,
        closed: 5,
        in_progress: 0,
      });
    });
  });

  describe('LRU cache', () => {
    it('should evict oldest accessed ticket when exceeding max limit', () => {
      for (let i = 1; i <= 21; i++) {
        store.tickets.update((m) => {
          const newMap = new Map(m);
          newMap.set(`t${i}`, { id: `t${i}`, status: 'open' } as unknown as Called);
          return newMap;
        });
        store.selectTicket(`t${i}`);
      }

      const ticketsMap = store.tickets();
      expect(ticketsMap.size).toBe(20);
      expect(ticketsMap.has('t1')).toBe(false);
      expect(ticketsMap.has('t21')).toBe(true);
    });
  });

  describe('Selectors', () => {
    it('filteredTickets should return sorted and filtered tickets', () => {
      store.tickets.set(
        new Map([
          [
            't1',
            {
              id: 't1',
              status: 'open',
              last_message: { created_at: '2023-01-01T00:00:00Z' },
            } as unknown as Called,
          ],
          [
            't2',
            {
              id: 't2',
              status: 'closed',
              last_message: { created_at: '2023-01-02T00:00:00Z' },
            } as unknown as Called,
          ],
          [
            't3',
            {
              id: 't3',
              status: 'open',
              last_message: { created_at: '2023-01-03T00:00:00Z' },
            } as unknown as Called,
          ],
        ]),
      );

      store.setFilters({ status: 'open' });
      const filtered = store.filteredTickets();
      expect(filtered.length).toBe(2);
      expect(filtered[0].id).toBe('t3'); // newer last_message
      expect(filtered[1].id).toBe('t1');
    });
  });

  describe('applyBatch', () => {
    it('should process batched events efficiently applying them to signals', () => {
      // Start with empty store
      store.tickets.set(new Map());
      store.messages.set(new Map());
      store.countsDelta.set({ all: 0, open: 0, closed: 0, in_progress: 0, pending: 0 });

      // Spy on setters to verify they are called just once per batch, though they use update
      // so spy on the signal is harder, we'll verify the final state.

      const ticketsBefore = store.tickets();
      expect(ticketsBefore.size).toBe(0);

      store.applyBatch([
        {
          type: 'ticket.new',
          timestamp: Date.now(),
          data: { ticket: { id: 'tk1', status: 'open', last_message: { id: 'm1' } } },
        },
        {
          type: 'msg.received',
          timestamp: Date.now() + 10,
          data: { ticket_id: 'tk1', message: { id: 'm2', content: 'hello' } },
        },
        {
          type: 'ticket.updated',
          timestamp: Date.now() + 20,
          data: { ticket_id: 'tk1', ticket: { status: 'in_progress', unread: true } },
        },
      ]);

      const ticketsAfter = store.tickets();
      expect(ticketsAfter.size).toBe(1);

      const tk = ticketsAfter.get('tk1');
      expect(tk).toBeDefined();
      expect(tk?.status).toBe('in_progress');
      const tkWithUnread = tk as (Called & { unread?: boolean }) | undefined;
      expect(tkWithUnread?.unread).toBe(true);

      const msgs = store.messages().get('tk1');
      expect(msgs).toBeDefined();
      expect(msgs?.length).toBe(1);
      expect(msgs?.[0].id).toBe('m2');

      const delta = store.countsDelta();
      // Should have processed delta properly from these events
      expect(delta.all).toBe(1);
      expect(delta.open).toBe(1); // the logic for status changes in real implementation might just be simpler map merge
    });

    it('should update edited messages in place and refresh quoted previews', () => {
      const originalMessage: CalledMessage = {
        id: 'm1',
        ticket_id: 'tk1',
        type: 'text',
        direction: 'incoming',
        content: 'texto antigo',
        created_at: '2026-03-17T10:00:00.000Z',
      };

      const replyMessage: CalledMessage = {
        id: 'm2',
        ticket_id: 'tk1',
        type: 'text',
        direction: 'outgoing',
        content: 'respondendo',
        created_at: '2026-03-17T10:01:00.000Z',
        quoted_message: {
          id: 'm1',
          content: 'texto antigo',
          direction: 'incoming',
          sender_name: 'Contato',
        },
      };

      store.messages.set(new Map([['tk1', [originalMessage, replyMessage]]]));

      store.applyBatch([
        {
          type: 'msg.edit',
          timestamp: Date.parse('2026-03-17T10:05:00.000Z'),
          data: {
            ticket_id: 'tk1',
            message_id: 'm1',
            content: 'texto novo',
            is_edited: true,
          },
        },
      ]);

      const updatedMessages = store.messages().get('tk1') ?? [];
      expect(updatedMessages).toHaveLength(2);

      const updatedOriginal = updatedMessages.find((message) => String(message.id) === 'm1');
      expect(updatedOriginal?.content).toBe('texto novo');
      expect(updatedOriginal?.is_edited).toBe(true);
      expect(updatedOriginal?.edited_at).toBe('2026-03-17T10:05:00.000Z');
      expect(updatedOriginal?.edit_history).toEqual([
        {
          content: 'texto antigo',
          edited_at: '2026-03-17T10:05:00.000Z',
        },
      ]);

      const updatedReply = updatedMessages.find((message) => String(message.id) === 'm2');
      expect(updatedReply?.quoted_message?.content).toBe('texto novo');
      expect(updatedReply?.quoted_message?.is_edited).toBe(true);
      expect(updatedReply?.quoted_message?.edited_at).toBe('2026-03-17T10:05:00.000Z');
    });
  });
});
