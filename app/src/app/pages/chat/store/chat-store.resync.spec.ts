import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { describe, expect, it, beforeEach, vi } from 'vitest';
import { ChatStore } from './chat.store';
import { CalledService } from '@core/services/called.service';
import { CalledMessageService, type PaginationMeta } from '@core/services/called-message.service';

const buildPaginationMeta = (
  currentPage: number,
  lastPage: number,
  hasMore: boolean,
  perPage = 100,
  total = perPage,
): PaginationMeta => ({
  current_page: currentPage,
  last_page: lastPage,
  per_page: perPage,
  total,
  has_more: hasMore,
});

interface StoreHarness {
  store: ChatStore;
  calledMessageServiceMock: { list: ReturnType<typeof vi.fn> };
}

function createStoreHarness(): StoreHarness {
  const calledServiceMock = {
    get: vi.fn().mockReturnValue(of({ data: { id: 'ticket-1', status: 'open' } })),
  };

  const calledMessageServiceMock = {
    list: vi
      .fn()
      .mockReturnValue(
        of({ data: { messages: [], meta: buildPaginationMeta(1, 1, false, 100, 0) } }),
      ),
  };

  TestBed.configureTestingModule({
    providers: [
      ChatStore,
      { provide: CalledService, useValue: calledServiceMock },
      { provide: CalledMessageService, useValue: calledMessageServiceMock },
    ],
  });

  return {
    store: TestBed.inject(ChatStore),
    calledMessageServiceMock,
  };
}

describe('ChatStore deterministic resync since cursor', () => {
  let store: ChatStore;
  let calledMessageServiceMock: { list: ReturnType<typeof vi.fn> };

  beforeEach(() => {
    ({ store, calledMessageServiceMock } = createStoreHarness());
  });

  it('resyncs selected ticket with additive since cursor on reconnect', () => {
    store.selectTicket('ticket-1');
    store.messages.set(
      new Map([
        [
          'ticket-1',
          [
            {
              id: 'm-old',
              content: 'persisted message',
              created_at: '2026-03-13T12:00:00.000Z',
            },
          ],
        ],
      ]),
    );

    calledMessageServiceMock.list.mockReturnValue(
      of({
        data: {
          messages: [
            {
              id: 'm-old',
              content: 'stale replay',
              created_at: '2026-03-13T11:59:00.000Z',
            },
            {
              id: 'm-new',
              content: 'missing message',
              created_at: '2026-03-13T12:01:00.000Z',
            },
          ],
          meta: buildPaginationMeta(1, 1, false, 100, 2),
        },
      }),
    );

    store.resyncSelectedTicketOnReconnect();

    expect(calledMessageServiceMock.list).toHaveBeenCalledWith('ticket-1', {
      page: 1,
      limit: 100,
      since: 'm-old',
    });

    const merged = store.messages().get('ticket-1') ?? [];
    expect(merged).toHaveLength(2);
    expect(merged.find((message) => String(message.id) === 'm-old')?.content).toBe(
      'persisted message',
    );
    expect(merged.find((message) => String(message.id) === 'm-new')?.content).toBe(
      'missing message',
    );
  });
});

describe('ChatStore deterministic resync pagination', () => {
  let store: ChatStore;
  let calledMessageServiceMock: { list: ReturnType<typeof vi.fn> };

  beforeEach(() => {
    ({ store, calledMessageServiceMock } = createStoreHarness());
  });

  it('loads all resync pages when backlog is greater than the per-page limit', () => {
    store.selectTicket('ticket-1');

    const firstPageMessages = Array.from({ length: 100 }, (_, index) => ({
      id: `m-${index + 1}`,
      content: `message ${index + 1}`,
      created_at: new Date(Date.UTC(2026, 2, 13, 12, index, 0)).toISOString(),
    }));

    const secondPageMessages = Array.from({ length: 25 }, (_, index) => ({
      id: `m-${index + 101}`,
      content: `message ${index + 101}`,
      created_at: new Date(Date.UTC(2026, 2, 13, 14, index, 0)).toISOString(),
    }));

    calledMessageServiceMock.list
      .mockReturnValueOnce(
        of({
          data: {
            messages: firstPageMessages,
            meta: buildPaginationMeta(1, 2, true, 100, 125),
          },
        }),
      )
      .mockReturnValueOnce(
        of({
          data: {
            messages: secondPageMessages,
            meta: buildPaginationMeta(2, 2, false, 100, 125),
          },
        }),
      );

    store.resyncSelectedTicketOnReconnect();

    expect(calledMessageServiceMock.list).toHaveBeenNthCalledWith(1, 'ticket-1', {
      page: 1,
      limit: 100,
    });
    expect(calledMessageServiceMock.list).toHaveBeenNthCalledWith(2, 'ticket-1', {
      page: 2,
      limit: 100,
    });

    const merged = store.messages().get('ticket-1') ?? [];
    expect(merged).toHaveLength(125);
    expect(merged[0]?.id).toBe('m-125');
    expect(merged[124]?.id).toBe('m-1');
  });
});

describe('ChatStore reconnect consistency', () => {
  let store: ChatStore;

  beforeEach(() => {
    ({ store } = createStoreHarness());
  });

  it('ignores stale status downgrades after reconnect processing', () => {
    store.messages.set(
      new Map([
        [
          'ticket-1',
          [
            {
              id: 'm-1',
              status: 'read',
            },
          ],
        ],
      ]),
    );

    store.applyBatch([
      {
        type: 'msg.status',
        timestamp: Date.now(),
        data: {
          ticket_id: 'ticket-1',
          message_id: 'm-1',
          status: 'sent',
        },
      },
    ]);

    const status = store.messages().get('ticket-1')?.[0]?.status;
    expect(status).toBe('read');
  });

  it('deduplicates repeated msg.received events for the same message id', () => {
    store.messages.set(
      new Map([
        [
          'ticket-1',
          [
            {
              id: 'm-1',
              content: 'newest copy',
              created_at: '2026-03-13T12:00:00.000Z',
            },
          ],
        ],
      ]),
    );

    store.applyBatch([
      {
        type: 'msg.received',
        timestamp: Date.now(),
        data: {
          ticket_id: 'ticket-1',
          message: {
            id: 'm-1',
            content: 'older copy',
            created_at: '2026-03-13T11:59:00.000Z',
          },
        },
      },
    ]);

    const messages = store.messages().get('ticket-1') ?? [];
    expect(messages).toHaveLength(1);
    expect(messages[0]?.content).toBe('newest copy');
  });
});
