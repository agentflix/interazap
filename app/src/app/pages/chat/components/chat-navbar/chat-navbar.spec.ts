import { describe, it, expect, beforeEach, afterEach, vi, type Mock } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { signal } from '@angular/core';
import { of, throwError } from 'rxjs';
import { ChatNavbar } from './chat-navbar';
import { type Called, CalledService } from 'src/app/core/services/called.service';
import { DepartmentService } from 'src/app/core/services/department.service';
import type { Department } from '@core/models/department.model';
import { UserService } from 'src/app/core/services/user.service';
import { AuthStoreService } from 'src/app/core/services/auth-store.service';
import { toast } from 'ngx-sonner';
import { provideIcons } from '@ng-icons/core';
import { lucideChevronsLeft, lucideLogOut, lucideVideo } from '@ng-icons/lucide';

describe('ChatNavbar', () => {
  let component: ChatNavbar;
  let fixture: ComponentFixture<ChatNavbar>;
  let router: { navigate: Mock };
  let calledService: { close: Mock; transfer: Mock; transferToUser: Mock };
  let departmentService: { list: Mock };
  let userService: { list: Mock };
  let authStore: AuthStoreService;

  const mockUser = {
    id: 'user-1',
    name: 'Test User',
    email: 'test@example.com',
    department_id: 'dept-1',
    is_active: true,
  };

  const mockCalled: Called = {
    id: 'called-1',
    protocol: 'TICKET-001',
    status: 'open',
    contact: {
      id: 'contact-1',
      name: 'John Doe',
      phone: '+5511999999999',
      whatsapp: '+5511999999999',
    },
  } as Called;

  const mockDepartment: Department = {
    id: 'dept-1',
    name: 'Support',
    is_active: true,
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-01T00:00:00Z',
  } as Department;

  beforeEach(async () => {
    router = { navigate: vi.fn() };

    calledService = {
      close: vi.fn().mockReturnValue(of({ data: mockCalled })),
      transfer: vi.fn().mockReturnValue(of({ data: mockCalled })),
      transferToUser: vi.fn().mockReturnValue(of({ data: { id: 'transfer-1' } })),
    };

    departmentService = {
      list: vi.fn().mockReturnValue(
        of({
          success: true,
          data: [mockDepartment],
          meta: {
            current_page: 1,
            from: 1,
            last_page: 1,
            per_page: 100,
            to: 1,
            total: 1,
          },
        }),
      ),
    };

    userService = {
      list: vi.fn().mockReturnValue(
        of({
          data: [mockUser],
          meta: { total: 1, per_page: 100, current_page: 1, last_page: 1 },
        }),
      ),
    };

    authStore = {
      user: signal(mockUser),
    } as unknown as AuthStoreService;

    vi.spyOn(toast, 'success').mockImplementation(() => '' as never);
    vi.spyOn(toast, 'error').mockImplementation(() => '' as never);

    await TestBed.configureTestingModule({
      imports: [ChatNavbar],
      providers: [
        { provide: Router, useValue: router },
        { provide: CalledService, useValue: calledService },
        { provide: DepartmentService, useValue: departmentService },
        { provide: UserService, useValue: userService },
        { provide: AuthStoreService, useValue: authStore },
        provideIcons({ lucideChevronsLeft, lucideLogOut, lucideVideo }),
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(ChatNavbar);
    component = fixture.componentInstance;
    component.called = mockCalled;
    fixture.detectChanges();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('Contact Information Display', () => {
    it('should display contact name', () => {
      expect(component.contactName).toBe('John Doe');
    });

    it('should display fallback for missing contact name', () => {
      component.called = { ...mockCalled, contact: { ...mockCalled.contact!, name: '' } };
      expect(component.contactName).toBe('Contato sem nome');
    });

    it('should display contact phone', () => {
      expect(component.contactPhone).toBe('+5511999999999');
    });

    it('should display fallback for missing phone', () => {
      component.called = {
        ...mockCalled,
        contact: { ...mockCalled.contact!, phone: '', whatsapp: '' },
      };
      expect(component.contactPhone).toBe('Sem telefone');
    });

    it('should generate contact initials correctly', () => {
      expect(component.contactInitials).toBe('JD');
    });

    it('should handle single name for initials', () => {
      component.called = { ...mockCalled, contact: { ...mockCalled.contact!, name: 'John' } };
      expect(component.contactInitials).toBe('JO');
    });

    it('should use protocol for initials when name is empty', () => {
      component.called = {
        ...mockCalled,
        protocol: 'TICKET-001',
        contact: { ...mockCalled.contact!, name: '' },
      };
      expect(component.contactInitials).toBe('TI');
    });
  });

  describe('Status Display', () => {
    it('should display correct status label for "open"', () => {
      component.called = { ...mockCalled, status: 'open' };
      expect(component.statusLabel).toBe('Aberto');
    });

    it('should display correct status label for "pending"', () => {
      component.called = { ...mockCalled, status: 'pending' };
      expect(component.statusLabel).toBe('Pendente');
    });

    it('should display correct status label for "in_progress"', () => {
      component.called = { ...mockCalled, status: 'in_progress' };
      expect(component.statusLabel).toBe('Em atendimento');
    });

    it('should display correct status label for "closed"', () => {
      component.called = { ...mockCalled, status: 'closed' };
      expect(component.statusLabel).toBe('Encerrado');
    });

    it('should return correct status class for "open"', () => {
      component.called = { ...mockCalled, status: 'open' };
      expect(component.statusClass).toBe('bg-info/10 text-info');
    });

    it('should return correct status class for "pending"', () => {
      component.called = { ...mockCalled, status: 'pending' };
      expect(component.statusClass).toBe('bg-warning/10 text-warning');
    });
  });

  describe('Transfer Actions', () => {
    it('should open transfer modal and load data', () => {
      component.openTransfer();

      expect(component.isTransferOpen()).toBe(true);
      expect(departmentService.list).toHaveBeenCalled();
      expect(userService.list).toHaveBeenCalled();
    });

    it('should close transfer modal and reset state', () => {
      component.selectedDepartmentId.set('dept-1');
      component.selectedUserId.set('user-1');
      component.isTransferLoading.set(true);

      component.closeTransfer();

      expect(component.isTransferOpen()).toBe(false);
      expect(component.selectedDepartmentId()).toBeNull();
      expect(component.selectedUserId()).toBeNull();
      expect(component.isTransferLoading()).toBe(false);
    });

    it('should filter users by department', () => {
      component.departments.set([mockDepartment]);
      component.users.set([
        { ...mockUser, id: 'user-1', department_id: 'dept-1' },
        { ...mockUser, id: 'user-2', department_id: 'dept-2' },
      ]);
      component.selectedDepartmentId.set('dept-1');

      const filtered = component.filteredUsers();
      expect(filtered.length).toBe(1);
      expect(filtered[0].id).toBe('user-1');
    });

    it('should require reason for user to user transfer in chat navbar', () => {
      component.selectedUserId.set('user-1');
      component.transferReasonControl.setValue('   ');

      component.confirmTransfer();

      expect(calledService.transferToUser).not.toHaveBeenCalled();
      expect(toast.error).toHaveBeenCalledWith('Informe o motivo da transferência.');
    });

    it('should call dedicated transfer endpoint from chat navbar user flow', async () => {
      component.selectedUserId.set('user-1');
      component.transferReasonControl.setValue('Repasse para analista especialista');

      component.confirmTransfer();

      await new Promise((resolve) => setTimeout(resolve, 100));

      expect(calledService.transferToUser).toHaveBeenCalledWith('called-1', {
        to_user_id: 'user-1',
        reason: 'Repasse para analista especialista',
      });
      expect(toast.success).toHaveBeenCalledWith('Transferência realizada com sucesso!');
      expect(component.isTransferOpen()).toBe(false);
    });

    it('should preserve department transfer flow in chat navbar', async () => {
      component.selectedDepartmentId.set('dept-1');

      component.confirmTransfer();

      await new Promise((resolve) => setTimeout(resolve, 100));

      const payload = calledService.transfer.mock.calls[
        calledService.transfer.mock.calls.length - 1
      ][1] as {
        department_id?: string;
      };
      expect(payload.department_id).toBe('dept-1');
      expect(calledService.transferToUser).not.toHaveBeenCalled();
      expect(toast.success).toHaveBeenCalledWith('Transferência realizada com sucesso!');
    });

    it('should handle transfer error', async () => {
      calledService.transfer.mockReturnValue(throwError(() => new Error('Transfer failed')));
      component.selectedDepartmentId.set('dept-1');

      component.confirmTransfer();

      await new Promise((resolve) => setTimeout(resolve, 100));

      expect(toast.error).toHaveBeenCalledWith('Erro ao realizar transferência.');
      expect(component.isTransferLoading()).toBe(false);
    });

    it('should not transfer without selection', () => {
      component.confirmTransfer();
      expect(calledService.transfer).not.toHaveBeenCalled();
    });

    it('should handle department change and reset user if needed', () => {
      component.users.set([
        { ...mockUser, id: 'user-1', department_id: 'dept-1' },
        { ...mockUser, id: 'user-2', department_id: 'dept-2' },
      ]);
      component.selectedUserId.set('user-1');

      component.onDepartmentChange('dept-2');

      expect(component.selectedDepartmentId()).toBe('dept-2');
      expect(component.selectedUserId()).toBeNull();
    });

    it('should handle user change and auto-select department', () => {
      component.users.set([{ ...mockUser, id: 'user-1', department_id: 'dept-1' }]);

      component.onUserChange('user-1');

      expect(component.selectedUserId()).toBe('user-1');
      expect(component.selectedDepartmentId()).toBe('dept-1');
    });
  });

  describe('Close Ticket Actions', () => {
    it('should open close confirmation modal', () => {
      component.openCloseModal();
      expect(component.isCloseModalOpen()).toBe(true);
    });

    it('should close confirmation modal', () => {
      component.isCloseModalOpen.set(true);
      component.closeCloseModal();
      expect(component.isCloseModalOpen()).toBe(false);
    });

    it('should close ticket successfully', async () => {
      component.confirmClose();

      await new Promise((resolve) => setTimeout(resolve, 100));

      expect(calledService.close).toHaveBeenCalledWith('called-1');
      expect(toast.success).toHaveBeenCalledWith('Atendimento encerrado com sucesso!');
      expect(router.navigate).toHaveBeenCalledWith(['/chat']);
    });

    it('should handle close ticket error', async () => {
      calledService.close.mockReturnValue(throwError(() => new Error('Close failed')));
      component.confirmClose();

      await new Promise((resolve) => setTimeout(resolve, 100));

      expect(toast.error).toHaveBeenCalledWith('Erro ao encerrar atendimento.');
      expect(component.isClosing()).toBe(false);
    });

    it('should not close ticket when called is null', () => {
      component.called = null;
      component.confirmClose();
      expect(calledService.close).not.toHaveBeenCalled();
    });
  });

  describe('Navigation', () => {
    it('should navigate back to chat list', () => {
      component.closeChat();
      expect(router.navigate).toHaveBeenCalledWith(['/chat']);
    });
  });
});
