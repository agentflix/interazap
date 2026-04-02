import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import {
  type Called,
  type CalledCounts,
  CalledService,
} from 'src/app/core/services/called.service';
import { ChatRefreshService } from 'src/app/core/services/chat-refresh.service';
import { ChatStore } from '../store/chat.store';
import { ChatTicketListService } from './chat-ticket-list.service';

const createMockTicket = (id: string, status: Called['status'] = 'open'): Called =>
  ({
    id,
    status,
    contact: { id: 'contact-1', name: 'John Doe', phone: '+5511999999999' },
    assigned_user: { id: 'user-1', name: 'Agent 1' },
    user: { id: 'user-1', name: 'Agent 1' },
  }) as Called;

class CalledServiceStub {
  list = vi.fn();
  get = vi.fn();
}

class ChatRefreshServiceStub {
  refreshTick = vi.fn().mockReturnValue(0);
  request = vi.fn();
}

describe('ChatTicketListService', () => {
  let service: ChatTicketListService;
  let calledService: CalledServiceStub;
  let chatRefresh: ChatRefreshServiceStub;

  beforeEach(() => {
    vi.useFakeTimers();

    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        { provide: CalledService, useClass: CalledServiceStub },
        { provide: ChatRefreshService, useClass: ChatRefreshServiceStub },
      ],
    });

    service = TestBed.runInInjectionContext(() => new ChatTicketListService());
    calledService = TestBed.runInInjectionContext(
      () => TestBed.inject(CalledService) as unknown as CalledServiceStub,
    );
    chatRefresh = TestBed.runInInjectionContext(
      () => TestBed.inject(ChatRefreshService) as unknown as ChatRefreshServiceStub,
    );
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  describe('loadTickets', () => {
    it('fills tickets and counts on success', () => {
      const mockTickets = [createMockTicket('1', 'open'), createMockTicket('2', 'pending')];
      calledService.list.mockReturnValue(
        of({
          data: mockTickets,
          counts: { all: 2, open: 1, pending: 1, closed: 0, in_progress: 0 },
        }),
      );

      service.loadTickets();

      expect(service.tickets()).toEqual(mockTickets);
      expect(service.counts()).toEqual({ all: 2, open: 1, pending: 1, closed: 0, in_progress: 0 });
      expect(service.loadingTickets()).toBe(false);
    });

    it('clears loadingTickets on error', () => {
      calledService.list.mockReturnValue(throwError(() => new Error('api error')));

      service.loadTickets();

      expect(service.loadingTickets()).toBe(false);
    });

    it('calls onComplete callback if provided', () => {
      calledService.list.mockReturnValue(
        of({ data: [], counts: { all: 0, open: 0, pending: 0, closed: 0, in_progress: 0 } }),
      );
      const cb = vi.fn();
      service.loadTickets(cb);
      expect(cb).toHaveBeenCalled();
    });
  });

  describe('setSearchTerm', () => {
    it('updates searchTerm signal and reloads tickets', () => {
      calledService.list.mockReturnValue(
        of({ data: [], counts: { all: 0, open: 0, pending: 0, closed: 0, in_progress: 0 } }),
      );

      service.setSearchTerm('john');

      expect(service.searchTerm()).toBe('john');
      expect(calledService.list).toHaveBeenCalledWith(expect.objectContaining({ search: 'john' }));
    });
  });

  describe('setTransientStatusOverride', () => {
    it('applies override and removes after TTL', () => {
      const ticket = createMockTicket('1', 'open');
      calledService.list.mockReturnValue(
        of({
          data: [ticket],
          counts: { all: 1, open: 1, pending: 0, closed: 0, in_progress: 0 },
        }),
      );

      service.loadTickets();
      service.setTransientStatusOverride('1', 'in_progress', 1000);

      const result = service.applyTransientStatusOverrides([ticket]);
      expect(result[0].status).toBe('in_progress');

      vi.advanceTimersByTime(1000);

      const afterResult = service.applyTransientStatusOverrides([ticket]);
      expect(afterResult[0].status).toBe('open');
    });
  });

  describe('applyTransientStatusOverrides', () => {
    it('merges overrides onto tickets', () => {
      const tickets = [createMockTicket('1', 'open'), createMockTicket('2', 'open')];
      service.setTransientStatusOverride('1', 'in_progress', 999999);

      const result = service.applyTransientStatusOverrides(tickets);

      expect(result[0].status).toBe('in_progress');
      expect(result[1].status).toBe('open');
    });

    it('returns original list when no overrides', () => {
      const tickets = [createMockTicket('1', 'open')];
      const result = service.applyTransientStatusOverrides(tickets);
      expect(result).toBe(tickets);
    });
  });

  describe('hydrateTicket', () => {
    it('merges full ticket into the list', () => {
      const partial: Called = {
        id: '1',
        status: 'pending',
      } as Called;
      const full: Called = {
        id: '1',
        status: 'open',
        contact: { id: 'contact-1', name: 'John Doe', phone: '+5511999999999' },
        assigned_user: { id: 'user-1', name: 'Agent 1' },
        user: { id: 'user-1', name: 'Agent 1' },
      } as Called;

      calledService.list.mockReturnValue(
        of({ data: [partial], counts: { all: 1, open: 0, pending: 1, closed: 0, in_progress: 0 } }),
      );
      calledService.get.mockReturnValue(of({ data: full }));

      service.loadTickets();
      service.hydrateTicket('1');

      const tickets = service.tickets();
      expect(tickets.length).toBe(1);
      expect(tickets[0].status).toBe('open');
      expect(tickets[0].contact).toEqual({
        id: 'contact-1',
        name: 'John Doe',
        phone: '+5511999999999',
      });
    });
  });

  describe('deriveCounts', () => {
    it('counts tickets by status correctly', () => {
      const tickets = [
        createMockTicket('1', 'open'),
        createMockTicket('2', 'open'),
        createMockTicket('3', 'pending'),
        createMockTicket('4', 'closed'),
        createMockTicket('5', 'in_progress'),
      ];

      const counts = service.deriveCounts(tickets);

      expect(counts.all).toBe(5);
      expect(counts.open).toBe(3); // 2 open + 1 in_progress
      expect(counts.pending).toBe(1);
      expect(counts.closed).toBe(1);
    });
  });

  describe('syncTicketsToStore (via loadTickets)', () => {
    it('syncs loaded tickets into the realtime store Map', () => {
      const mockTickets = [createMockTicket('1', 'open'), createMockTicket('2', 'pending')];
      calledService.list.mockReturnValue(
        of({
          data: mockTickets,
          counts: { all: 2, open: 1, pending: 1, closed: 0, in_progress: 0 },
        }),
      );

      const realtimeStore = TestBed.inject(ChatStore);

      service.loadTickets();

      const storeTickets = realtimeStore.tickets();
      expect(storeTickets.get('1')).toBeDefined();
      expect(storeTickets.get('1')?.status).toBe('open');
      expect(storeTickets.get('2')).toBeDefined();
      expect(storeTickets.get('2')?.status).toBe('pending');
    });

    it('merges new tickets without removing existing ones in the store', () => {
      const realtimeStore = TestBed.inject(ChatStore);
      // Pre-populate store with existing ticket
      realtimeStore.tickets.update((m) => {
        const next = new Map(m);
        next.set('existing', createMockTicket('existing', 'closed'));
        return next;
      });

      calledService.list.mockReturnValue(
        of({
          data: [createMockTicket('new-1', 'open')],
          counts: { all: 1, open: 1, pending: 0, closed: 0, in_progress: 0 },
        }),
      );

      service.loadTickets();

      const storeTickets = realtimeStore.tickets();
      expect(storeTickets.has('existing')).toBe(true);
      expect(storeTickets.has('new-1')).toBe(true);
    });
  });

  describe('hydrateTicket syncs to store', () => {
    it('syncs hydrated ticket into the realtime store', () => {
      const full = createMockTicket('1', 'open');
      calledService.list.mockReturnValue(
        of({
          data: [createMockTicket('1', 'pending')],
          counts: { all: 1, open: 0, pending: 1, closed: 0, in_progress: 0 },
        }),
      );
      calledService.get.mockReturnValue(of({ data: full }));

      const realtimeStore = TestBed.inject(ChatStore);

      service.loadTickets();
      service.hydrateTicket('1');

      const stored = realtimeStore.tickets().get('1');
      expect(stored).toBeDefined();
      expect(stored?.status).toBe('open');
    });
  });
});
