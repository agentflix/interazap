import { TestBed } from '@angular/core/testing';
import { ChatRealtimeStore } from './chat-realtime.store';

describe('ChatRealtimeStore memory safeguards', () => {
  let store: ChatRealtimeStore;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [ChatRealtimeStore],
    });
    store = TestBed.inject(ChatRealtimeStore);
  });

  it('keeps a ring buffer of at most 500 events', () => {
    for (let index = 0; index < 550; index += 1) {
      store.push({
        type: 'typing',
        payload: {
          ticket_id: `ticket-${index}`,
          is_typing: true,
        },
      });
    }

    expect(store.events().length).toBe(500);
    expect(store.events()[0]).toMatchObject({
      payload: { ticket_id: 'ticket-50' },
    });
  });

  it('evicts stale lastByTicket entries beyond 500 keys', () => {
    for (let index = 0; index < 550; index += 1) {
      store.push({
        type: 'new',
        payload: {
          ticket_id: `ticket-${index}`,
          message: { id: `message-${index}` },
        },
      });
    }

    const lastByTicket = store.lastByTicket();
    expect(lastByTicket.size).toBe(500);
    expect(lastByTicket.has('ticket-0')).toBe(false);
    expect(lastByTicket.has('ticket-549')).toBe(true);
  });
});
