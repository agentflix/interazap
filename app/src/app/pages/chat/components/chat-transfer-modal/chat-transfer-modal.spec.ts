import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { type Called } from 'src/app/core/services/called.service';
import { UserService } from 'src/app/core/services/user.service';
import { ChatTransferModalComponent } from './chat-transfer-modal';

const createMockTicket = (overrides: Partial<Called> = {}): Called =>
  ({
    id: 'ticket-1',
    status: 'open',
    contact: { id: 'contact-1', name: 'John Doe', phone: '+5511999999999' },
    assigned_user: { id: 'user-1', name: 'Agent 1', email: 'agent1@test.com' },
    user: { id: 'user-1', name: 'Agent 1', email: 'agent1@test.com' },
    ...overrides,
  }) as Called;

class UserServiceStub {
  list = vi.fn().mockReturnValue(
    of({
      data: [
        { id: 'user-2', name: 'Agent 2', email: 'agent2@test.com', is_active: true },
        { id: 'user-3', name: 'Agent 3', email: 'agent3@test.com', is_active: true },
      ],
    }),
  );
}

describe('ChatTransferModalComponent', () => {
  let userServiceStub: UserServiceStub;

  beforeEach(() => {
    void TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: UserService, useClass: UserServiceStub },
      ],
    }).compileComponents();

    userServiceStub = TestBed.inject(UserService) as unknown as UserServiceStub;
  });

  it('creates component', () => {
    const fixture = TestBed.createComponent(ChatTransferModalComponent);
    const component = fixture.componentInstance;
    expect(component).toBeTruthy();
  });

  describe('transferUserOptions', () => {
    it('filters out the current assigned user', () => {
      const fixture = TestBed.createComponent(ChatTransferModalComponent);
      const component = fixture.componentInstance;

      // Directly set the transferUsers signal for unit testing
      component.transferUsers.set([
        { id: 'user-1', name: 'Agent 1', email: 'agent1@test.com', is_active: true },
        { id: 'user-2', name: 'Agent 2', email: 'agent2@test.com', is_active: true },
      ]);

      // ticket input defaults to null, so assigned_user.id is ''
      // but we need to test the filtering, so set a mock ticket with assigned_user
      fixture.componentRef.setInput(
        'ticket',
        createMockTicket({
          assigned_user: { id: 'user-1', name: 'Agent 1' },
        }),
      );
      fixture.detectChanges();

      const options = component.transferUserOptions();
      expect(options.length).toBe(1);
      expect(options[0].value).toBe('user-2');
    });

    it('returns all active users when no user is currently assigned', () => {
      const fixture = TestBed.createComponent(ChatTransferModalComponent);
      const component = fixture.componentInstance;

      component.transferUsers.set([
        { id: 'user-1', name: 'Agent 1', email: 'agent1@test.com', is_active: true },
        { id: 'user-2', name: 'Agent 2', email: 'agent2@test.com', is_active: true },
      ]);

      fixture.componentRef.setInput(
        'ticket',
        createMockTicket({
          assigned_user: undefined,
          user: undefined,
        }),
      );
      fixture.detectChanges();

      const options = component.transferUserOptions();
      expect(options.length).toBe(2);
    });
  });

  describe('confirm', () => {
    it('should disable confirm until user and reason are provided', () => {
      const fixture = TestBed.createComponent(ChatTransferModalComponent);
      const component = fixture.componentInstance;

      fixture.componentRef.setInput('ticket', createMockTicket());
      fixture.componentRef.setInput('isOpen', true);
      fixture.detectChanges();

      expect(component.canSubmit()).toBe(false);

      component.transferUserControl.setValue('user-2');
      fixture.detectChanges();
      expect(component.canSubmit()).toBe(false);

      component.transferReasonControl.setValue('Contexto de repasse');
      fixture.detectChanges();
      expect(component.canSubmit()).toBe(true);
    });

    it('should emit reason and selected user on confirm', () => {
      const fixture = TestBed.createComponent(ChatTransferModalComponent);
      const component = fixture.componentInstance;

      fixture.componentRef.setInput('ticket', createMockTicket());
      fixture.detectChanges();

      component.transferUserControl.setValue('user-2');
      component.transferReasonControl.setValue('Repasse para especialista');

      let emitted: { ticketId: string; toUserId: string; reason: string } | undefined;
      component.confirmed.subscribe((e) => (emitted = e));
      component.confirm();

      expect(emitted).toEqual({
        ticketId: 'ticket-1',
        toUserId: 'user-2',
        reason: 'Repasse para especialista',
      });
    });

    it('should block inputs and show loading label while submitting transfer', () => {
      const fixture = TestBed.createComponent(ChatTransferModalComponent);
      const component = fixture.componentInstance;

      fixture.componentRef.setInput('ticket', createMockTicket());
      fixture.componentRef.setInput('isOpen', true);
      fixture.componentRef.setInput('isSubmitting', true);
      fixture.detectChanges();

      const host = fixture.nativeElement as HTMLElement;
      expect(host.textContent).toContain('Transferindo...');
      expect(component.canSubmit()).toBe(false);
    });

    it('should preserve form data and show error message when transfer fails', () => {
      const fixture = TestBed.createComponent(ChatTransferModalComponent);
      const component = fixture.componentInstance;

      fixture.componentRef.setInput('ticket', createMockTicket());
      fixture.componentRef.setInput('isOpen', true);
      fixture.detectChanges();

      component.transferUserControl.setValue('user-2');
      component.transferReasonControl.setValue('Motivo preenchido');
      fixture.componentRef.setInput(
        'submitError',
        'Não foi possível transferir o chamado. Tente novamente.',
      );
      fixture.detectChanges();

      const host = fixture.nativeElement as HTMLElement;
      expect(host.textContent).toContain('Não foi possível transferir o chamado. Tente novamente.');
      expect(component.transferUserControl.value).toBe('user-2');
      expect(component.transferReasonControl.value).toBe('Motivo preenchido');
    });

    it('should show empty state when no transfer users are available', () => {
      userServiceStub.list.mockReturnValue(of({ data: [] }));

      const fixture = TestBed.createComponent(ChatTransferModalComponent);
      const component = fixture.componentInstance;

      fixture.componentRef.setInput('ticket', createMockTicket());
      fixture.componentRef.setInput('isOpen', true);
      fixture.detectChanges();

      const host = fixture.nativeElement as HTMLElement;
      expect(host.textContent).toContain('Nenhum atendente disponível para transferência.');
      expect(component.canSubmit()).toBe(false);
    });
  });
});
