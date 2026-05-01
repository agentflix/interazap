import { type DestroyRef, signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { type Called, CalledService } from 'src/app/core/services/called.service';
import { ChatTicketListService } from './chat-ticket-list.service';
import { ChatTicketTransferService } from './chat-ticket-transfer.service';

class CalledServiceStub {
  transferToUser = vi.fn().mockReturnValue(of({ data: { id: 't1' } }));
  get = vi.fn().mockReturnValue(of({ data: { id: 't1', status: 'open', updated_field: 'x' } }));
}

class ChatTicketListServiceStub {
  loadTickets = vi.fn();
}

const ticket: Called = { id: 't1', status: 'open' } as Called;

describe('ChatTicketTransferService', () => {
  let service: ChatTicketTransferService;
  let called: CalledServiceStub;
  let destroyRef: DestroyRef;

  beforeEach(() => {
    called = new CalledServiceStub();
    TestBed.configureTestingModule({
      providers: [
        ChatTicketTransferService,
        { provide: CalledService, useValue: called },
        { provide: ChatTicketListService, useValue: new ChatTicketListServiceStub() },
      ],
    });
    service = TestBed.inject(ChatTicketTransferService);
    destroyRef = { onDestroy: vi.fn() } as unknown as DestroyRef;
  });

  it('opens the modal for an open ticket', () => {
    service.openModal(ticket);
    expect(service.isModalOpen()).toBe(true);
    expect(service.error()).toBeNull();
  });

  it('does not open modal for closed ticket', () => {
    service.openModal({ ...ticket, status: 'closed' } as Called);
    expect(service.isModalOpen()).toBe(false);
  });

  it('confirms transfer and patches tickets list (happy path)', () => {
    const tickets = signal<Called[]>([ticket]);
    service.confirm({ ticketId: 't1', toUserId: 'u1', reason: 'r' }, destroyRef, tickets);
    expect(called.transferToUser).toHaveBeenCalledWith('t1', { to_user_id: 'u1', reason: 'r' });
    expect(called.get).toHaveBeenCalledWith('t1');
    expect(service.isLoading()).toBe(false);
    expect(service.isModalOpen()).toBe(false);
    expect(tickets()[0]).toMatchObject({ id: 't1' });
  });

  it('sets error on transfer failure', () => {
    called.transferToUser = vi.fn().mockReturnValue(throwError(() => new Error('boom')));
    const tickets = signal<Called[]>([ticket]);
    service.confirm({ ticketId: 't1', toUserId: 'u1', reason: 'r' }, destroyRef, tickets);
    expect(service.isLoading()).toBe(false);
    expect(service.error()).toContain('Não foi possível');
  });
});
