import { type DestroyRef, signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { of, throwError } from 'rxjs';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { type Called, type CalledCounts, CalledService } from 'src/app/core/services/called.service';
import { ChatTicketListService } from './chat-ticket-list.service';
import { ChatTicketCloseService } from './chat-ticket-close.service';

vi.mock('ngx-sonner', () => ({
  toast: { success: vi.fn(), error: vi.fn() },
}));

class CalledServiceStub {
  close = vi.fn().mockReturnValue(of({ data: { id: 't1', status: 'closed', closed_at: 'now' } }));
}
class TicketListStub {
  loadTickets = vi.fn();
}
class RouterStub {
  navigate = vi.fn().mockResolvedValue(true);
}

const baseTicket: Called = { id: 't1', status: 'open' } as Called;
const baseCounts: CalledCounts = { all: 1, pending: 0, open: 1, in_progress: 0, closed: 0 };

function setup() {
  const called = new CalledServiceStub();
  TestBed.configureTestingModule({
    providers: [
      ChatTicketCloseService,
      { provide: CalledService, useValue: called },
      { provide: ChatTicketListService, useValue: new TicketListStub() },
      { provide: Router, useValue: new RouterStub() },
    ],
  });
  return {
    service: TestBed.inject(ChatTicketCloseService),
    called,
    destroyRef: { onDestroy: vi.fn() } as unknown as DestroyRef,
  };
}

describe('ChatTicketCloseService', () => {
  beforeEach(() => TestBed.resetTestingModule());

  it('opens confirm for open ticket', () => {
    const { service } = setup();
    service.openConfirm(baseTicket);
    expect(service.isConfirmOpen()).toBe(true);
  });

  it('does not open confirm for closed ticket', () => {
    const { service } = setup();
    service.openConfirm({ ...baseTicket, status: 'closed' } as Called);
    expect(service.isConfirmOpen()).toBe(false);
  });

  it('confirms close optimistically and clears selection', () => {
    const { service, destroyRef } = setup();
    const tickets = signal<Called[]>([baseTicket]);
    const counts = signal<CalledCounts>(baseCounts);
    const selected = signal<string | null>('t1');

    service.confirm(baseTicket, destroyRef, tickets, counts, selected);

    expect(tickets()[0].status).toBe('closed');
    expect(counts().closed).toBe(1);
    expect(service.isClosing()).toBe(false);
    expect(selected()).toBeNull();
  });

  it('rolls back tickets/counts on API error', () => {
    const { service, called, destroyRef } = setup();
    called.close = vi.fn().mockReturnValue(throwError(() => new Error('boom')));
    const tickets = signal<Called[]>([baseTicket]);
    const counts = signal<CalledCounts>(baseCounts);
    const selected = signal<string | null>('t1');

    service.confirm(baseTicket, destroyRef, tickets, counts, selected);

    expect(tickets()[0].status).toBe('open');
    expect(counts()).toEqual(baseCounts);
    expect(service.isClosing()).toBe(false);
  });
});
