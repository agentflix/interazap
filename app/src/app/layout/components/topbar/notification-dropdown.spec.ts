import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { of, throwError, Subject } from 'rxjs';
import { LucideAngularModule } from 'lucide-angular';
import { NotificationDropdownComponent } from './notification-dropdown';
import { NotificationApiService } from '../../../core/services/notification-api.service';
import { type Notification } from '../../../shared/models/notification.model';

describe('NotificationDropdownComponent', () => {
  let fixture: ComponentFixture<NotificationDropdownComponent>;
  let component: NotificationDropdownComponent;

  const mockNotifications: Notification[] = [
    {
      id: 'uuid-1',
      tenant_id: 'tenant-1',
      user_id: 'user-1',
      type: 'new_ticket',
      title: 'Novo Ticket #123',
      body: 'Ticket criado por João Silva',
      created_at: new Date().toISOString(),
      read_at: null,
    },
    {
      id: 'uuid-2',
      tenant_id: 'tenant-1',
      user_id: 'user-1',
      type: 'ticket_assigned',
      title: 'Ticket Atribuído',
      body: 'Ticket #456 foi atribuído a você',
      created_at: new Date(Date.now() - 3600000).toISOString(),
      read_at: null,
    },
    {
      id: 'uuid-3',
      tenant_id: 'tenant-1',
      user_id: 'user-1',
      type: 'system',
      title: 'Manutenção Agendada',
      body: null,
      created_at: new Date(Date.now() - 86400000).toISOString(),
      read_at: '2026-03-27T10:00:00Z',
    },
  ];

  const apiServiceMock = {
    fetchUnread: vi.fn(),
    markAsRead: vi.fn(),
    markAllAsRead: vi.fn(),
  };

  beforeEach(async () => {
    apiServiceMock.fetchUnread.mockReturnValue(of({ data: [], unread_count: 0 }));
    apiServiceMock.markAsRead.mockReturnValue(of(undefined));

    await TestBed.configureTestingModule({
      imports: [NotificationDropdownComponent, LucideAngularModule],
      providers: [
        {
          provide: NotificationApiService,
          useValue: apiServiceMock,
        },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(NotificationDropdownComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should have empty notifications initially', () => {
    expect(component.notifications()).toEqual([]);
  });

  it('should fetch notifications when opening dropdown', () => {
    component.toggle();
    fixture.detectChanges();

    expect(apiServiceMock.fetchUnread).toHaveBeenCalledWith(10);
  });

  it('should display loading state while fetching', () => {
    const subject = new Subject<{ data: Notification[]; unread_count: number }>();
    apiServiceMock.fetchUnread.mockReturnValue(subject.asObservable());

    component.toggle();
    fixture.detectChanges();

    expect(component.loading()).toBe(true);

    subject.next({ data: mockNotifications, unread_count: 2 });
    subject.complete();
    fixture.detectChanges();

    expect(component.loading()).toBe(false);
    expect(component.notifications()).toHaveLength(3);
  });

  it('should display empty state when no notifications', () => {
    apiServiceMock.fetchUnread.mockReturnValue(of({ data: [], unread_count: 0 }));

    component.toggle();
    fixture.detectChanges();

    expect(component.notifications()).toEqual([]);
    expect(fixture.nativeElement.textContent).toContain('Nenhuma notificação');
  });

  it('should display notifications list with correct items', () => {
    apiServiceMock.fetchUnread.mockReturnValue(of({ data: mockNotifications, unread_count: 2 }));

    component.toggle();
    fixture.detectChanges();

    expect(component.notifications()).toHaveLength(3);
    expect(fixture.nativeElement.textContent).toContain('Novo Ticket #123');
    expect(fixture.nativeElement.textContent).toContain('Ticket Atribuído');
    expect(fixture.nativeElement.textContent).toContain('Manutenção Agendada');
  });

  it('should display error state when fetch fails', () => {
    apiServiceMock.fetchUnread.mockReturnValue(throwError(() => new Error('Network error')));

    component.toggle();
    fixture.detectChanges();

    expect(component.error()).toBe('Não foi possível carregar as notificações.');
    expect(fixture.nativeElement.textContent).toContain('Erro ao carregar notificações');
  });

  it('should call markAsRead when clicking unread notification', () => {
    apiServiceMock.fetchUnread.mockReturnValue(of({ data: mockNotifications, unread_count: 2 }));
    apiServiceMock.markAsRead.mockReturnValue(of(undefined as void));

    component.toggle();
    fixture.detectChanges();

    const buttons = fixture.nativeElement.querySelectorAll('button');
    const unreadButton = buttons[1]; // First notification item
    unreadButton.click();
    fixture.detectChanges();

    expect(apiServiceMock.markAsRead).toHaveBeenCalledWith('uuid-1');
  });

  it('should close dropdown after clicking notification', () => {
    apiServiceMock.fetchUnread.mockReturnValue(of({ data: mockNotifications, unread_count: 2 }));

    component.toggle();
    fixture.detectChanges();
    expect(component.open()).toBe(true);

    const buttons = fixture.nativeElement.querySelectorAll('button');
    buttons[1].click();
    fixture.detectChanges();

    expect(component.open()).toBe(false);
  });

  it('should close dropdown when clicking outside', () => {
    component.toggle();
    fixture.detectChanges();
    expect(component.open()).toBe(true);

    const event = new MouseEvent('click', { bubbles: true });
    Object.defineProperty(event, 'target', {
      value: document.createElement('div'),
      writable: false,
    });
    document.dispatchEvent(event);
    fixture.detectChanges();

    expect(component.open()).toBe(false);
  });

  it('should have unread count matching unread notifications', () => {
    apiServiceMock.fetchUnread.mockReturnValue(of({ data: mockNotifications, unread_count: 2 }));

    component.toggle();
    fixture.detectChanges();

    expect(component.unreadCount()).toBe(2); // uuid-1 and uuid-2 have read_at: null
  });
});
