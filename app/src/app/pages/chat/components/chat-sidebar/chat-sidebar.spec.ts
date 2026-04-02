import { describe, it, expect, beforeEach, vi } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { computed, signal } from '@angular/core';
import { type Observable, of, throwError } from 'rxjs';
import { ChatSidebar } from './chat-sidebar';
import { ContactService } from 'src/app/core/services/contact.service';
import { CalledService } from 'src/app/core/services/called.service';
import { CalledMessageService } from 'src/app/core/services/called-message.service';
import {
  type Instance,
  type InstanceListResponse,
  InstanceService,
} from 'src/app/core/services/instance.service';
import { ChatSoundService } from 'src/app/core/services/chat-sound.service';
import { ChatRefreshService } from 'src/app/core/services/chat-refresh.service';
import { ChatStartService } from 'src/app/core/services/chat-start.service';
import { RealtimeService } from 'src/app/core/services/realtime.service';
import { Router, ActivatedRoute } from '@angular/router';
import { type AuthUser, AuthStoreService } from 'src/app/core/services/auth-store.service';
import { toast } from 'ngx-sonner';

class ChatRefreshServiceStub {
  private readonly refreshSignal = signal(0);
  private readonly readTicketSignal = signal<string | null>(null);
  refreshTick = this.refreshSignal.asReadonly();
  readTicketId = this.readTicketSignal.asReadonly();
  request = vi.fn();
}

class ContactServiceStub {
  list = vi.fn().mockReturnValue(of({ data: [], meta: { total: 0 } }));
}

class CalledServiceStub {
  list = vi.fn().mockReturnValue(of({ data: [], meta: { total: 0 } }));
  create = vi.fn().mockReturnValue(of({ data: { id: 'called-1' } }));
  open = vi.fn().mockReturnValue(of(null));
  counts = vi.fn().mockReturnValue(of({}));
}

class CalledMessageServiceStub {
  send = vi.fn().mockReturnValue(of(null));
}

class InstanceServiceStub {
  list = vi.fn();
}

class ChatSoundServiceStub {
  private muted = signal(false);
  mutedState = computed((): boolean => this.muted());
  toggle(): void {
    this.muted.update((value: boolean): boolean => !value);
  }
}

class RealtimeServiceStub {
  connect = vi.fn();
  on(): Observable<unknown> {
    return of();
  }
}

class RouterStub {
  navigate = vi.fn();
  events = of({});
}

class AuthStoreServiceStub {
  private currentUser: AuthUser | null = {
    id: 'user-1',
    name: 'Test User',
    email: 'test@example.com',
    company_id: 'tenant-1',
    permissions: [],
  };

  user = (): AuthUser | null => this.currentUser;

  setUser(user: AuthUser | null): void {
    this.currentUser = user;
  }
}

describe('ChatSidebar – instance detection', (): void => {
  let component: ChatSidebar;
  let fixture: ComponentFixture<ChatSidebar>;
  let instanceService: InstanceServiceStub;
  let authStore: AuthStoreServiceStub;

  const responseFor = (items: Instance[]): InstanceListResponse => ({
    data: items,
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: items.length || 1,
      total: items.length,
    },
  });

  const createInstance = (id: string, status: string): Instance => ({
    id,
    name: `Instance ${id}`,
    connection_status: status,
    is_active: true,
  });

  const createInstanceWithSettings = (id: string): Instance => ({
    id,
    name: `Instance ${id}`,
    status: undefined,
    is_active: true,
    settings: {
      last_connection: {
        connected: true,
        logged_in: true,
      },
    },
  });

  beforeEach((): void => {
    TestBed.configureTestingModule({
      providers: [
        ChatRefreshService,
        ChatStartService,
        { provide: ContactService, useClass: ContactServiceStub },
        { provide: CalledService, useClass: CalledServiceStub },
        { provide: CalledMessageService, useClass: CalledMessageServiceStub },
        { provide: InstanceService, useClass: InstanceServiceStub },
        { provide: ChatSoundService, useClass: ChatSoundServiceStub },
        { provide: RealtimeService, useClass: RealtimeServiceStub },
        { provide: Router, useClass: RouterStub },
        { provide: AuthStoreService, useClass: AuthStoreServiceStub },
        {
          provide: ActivatedRoute,
          useValue: { params: of({}), snapshot: { paramMap: { get: (): string | null => null } } },
        },
      ],
    });

    instanceService = TestBed.inject(InstanceService) as unknown as InstanceServiceStub;
    authStore = TestBed.inject(AuthStoreService) as unknown as AuthStoreServiceStub;
    authStore.setUser({
      id: 'user-1',
      name: 'Tester',
      email: 'tester@example.com',
      company_id: 'tenant-1',
      permissions: [],
    });

    fixture = TestBed.createComponent(ChatSidebar);
    component = fixture.componentInstance;
    fixture.detectChanges();
    localStorage.clear();
  });

  it('auto-selects a single connected instance and hides empty state', (): void => {
    instanceService.list.mockReturnValue(of(responseFor([createInstance('1', 'connected')])));

    component.openStartModal();

    expect(component.instances().length).toBe(1);
    expect(component.selectedInstanceId()).toBe('1');
    expect(component.showInstanceEmptyState()).toBe(false);
    expect(component.instancesError()).toBeNull();
  });

  it('treats last_connection.connected as connected when status is unknown', (): void => {
    instanceService.list.mockReturnValue(of(responseFor([createInstanceWithSettings('55')])));

    component.openStartModal();

    expect(component.instances().length).toBe(1);
    expect(component.selectedInstanceId()).toBe('55');
    expect(component.showInstanceEmptyState()).toBe(false);
  });

  it('restores persisted selection when the instance is still connected', (): void => {
    const storageKey = 'chat:selectedInstanceId:tenant-1:user-1';
    localStorage.setItem(storageKey, '2');
    instanceService.list.mockReturnValue(
      of(responseFor([createInstance('2', 'connected'), createInstance('3', 'connected')])),
    );

    component.openStartModal();

    expect(component.selectedInstanceId()).toBe('2');
    expect(component.requiresInstanceSelection()).toBe(false);
  });

  it('requires manual selection when there are multiple connected instances without persisted choice', (): void => {
    instanceService.list.mockReturnValue(
      of(responseFor([createInstance('10', 'connected'), createInstance('11', 'connected')])),
    );

    component.openStartModal();

    expect(component.instances().length).toBe(2);
    expect(component.selectedInstanceId()).toBe('');
    expect(component.requiresInstanceSelection()).toBe(true);
  });

  it('shows empty state only when no connected instance exists', (): void => {
    instanceService.list.mockReturnValue(of(responseFor([])));

    component.openStartModal();

    expect(component.instances().length).toBe(0);
    expect(component.showInstanceEmptyState()).toBe(true);
    expect(component.instancesError()).toBeNull();
  });

  it('surfaces an error state when the instance request fails', (): void => {
    instanceService.list.mockReturnValue(throwError((): Error => new Error('fail')));

    component.openStartModal();

    expect(component.instances().length).toBe(0);
    expect(component.instancesError()).toBe('Não foi possível carregar as conexões de WhatsApp.');
    expect(component.showInstanceEmptyState()).toBe(false);
  });
});

describe('ChatSidebar – search and start flow', (): void => {
  let component: ChatSidebar;
  let fixture: ComponentFixture<ChatSidebar>;
  let calledService: CalledServiceStub;
  let messageService: CalledMessageServiceStub;
  let router: RouterStub;

  beforeEach((): void => {
    TestBed.configureTestingModule({
      providers: [
        ChatRefreshService,
        ChatStartService,
        { provide: ContactService, useClass: ContactServiceStub },
        { provide: CalledService, useClass: CalledServiceStub },
        { provide: CalledMessageService, useClass: CalledMessageServiceStub },
        { provide: InstanceService, useClass: InstanceServiceStub },
        { provide: ChatSoundService, useClass: ChatSoundServiceStub },
        { provide: RealtimeService, useClass: RealtimeServiceStub },
        { provide: Router, useClass: RouterStub },
        { provide: AuthStoreService, useClass: AuthStoreServiceStub },
        {
          provide: ActivatedRoute,
          useValue: { params: of({}), snapshot: { paramMap: { get: (): string | null => null } } },
        },
      ],
    });

    calledService = TestBed.inject(CalledService) as unknown as CalledServiceStub;
    messageService = TestBed.inject(CalledMessageService) as unknown as CalledMessageServiceStub;
    router = TestBed.inject(Router) as unknown as RouterStub;

    calledService = TestBed.inject(CalledService) as unknown as CalledServiceStub;
    messageService = TestBed.inject(CalledMessageService) as unknown as CalledMessageServiceStub;
    router = TestBed.inject(Router) as unknown as RouterStub;

    fixture = TestBed.createComponent(ChatSidebar);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('abre e fecha modal de busca sincronizando o termo', (): void => {
    component.searchTerm.set('cliente');

    component.openSearchModal();
    expect(component.isSearchModalOpen()).toBe(true);
    expect(component.searchQuery()).toBe('cliente');

    component.closeSearchModal();
    expect(component.isSearchModalOpen()).toBe(false);
    expect(component.searchQuery()).toBe('cliente');
  });

  it('atualiza consulta com trim e limpa filtros', (): void => {
    component.onSearchQueryInput('  novo  ');
    expect(component.searchQuery()).toBe('  novo  ');
    expect(component.searchTerm()).toBe('novo');

    component.clearSearch();
    expect(component.searchTerm()).toBe('');
    expect(component.searchQuery()).toBe('');
  });

  it('valida campos obrigatórios antes de iniciar chat', (): void => {
    component.startChat();
    expect(component.startError()).toBe('Selecione um contato.');

    component.selectedContactId.set('contact-1');
    component.startChat();
    expect(component.startError()).toBe('Selecione uma instância conectada.');

    component.selectedInstanceId.set('instance-1');
    component.startChat();
    expect(component.startError()).toBe('Digite uma mensagem inicial.');
  });

  it('reaproveita chamado existente e navega', (): void => {
    vi.spyOn(toast, 'info').mockImplementation(() => '' as never);
    calledService.list
      .mockReturnValueOnce(of({ data: [{ id: 'called-9' }], meta: { total: 1 } }))
      .mockReturnValueOnce(of({ data: [], meta: { total: 0 } }))
      .mockReturnValueOnce(of({ data: [], meta: { total: 0 } }));

    component.selectedContactId.set('contact-1');
    component.selectedInstanceId.set('instance-1');
    component.startMessage.set('Olá');
    component.startChat();

    expect(toast.info).toHaveBeenCalled();
    expect(router.navigate).toHaveBeenCalledWith(['/chat', 'called-9']);
    expect(messageService.send).not.toHaveBeenCalled();
  });
});

describe('ChatSidebar – Modal Management', (): void => {
  let component: ChatSidebar;
  let fixture: ComponentFixture<ChatSidebar>;

  beforeEach((): void => {
    TestBed.configureTestingModule({
      providers: [
        { provide: ContactService, useClass: ContactServiceStub },
        { provide: CalledService, useClass: CalledServiceStub },
        { provide: CalledMessageService, useClass: CalledMessageServiceStub },
        { provide: InstanceService, useClass: InstanceServiceStub },
        { provide: ChatSoundService, useClass: ChatSoundServiceStub },
        { provide: ChatRefreshService, useClass: ChatRefreshServiceStub },
        { provide: ChatStartService, useClass: ChatStartService },
        { provide: RealtimeService, useClass: RealtimeServiceStub },
        { provide: Router, useClass: RouterStub },
        { provide: AuthStoreService, useClass: AuthStoreServiceStub },
        {
          provide: ActivatedRoute,
          useValue: { params: of({}), snapshot: { paramMap: { get: (): string | null => null } } },
        },
      ],
    });

    const instanceService = TestBed.inject(InstanceService) as unknown as InstanceServiceStub;
    instanceService.list.mockReturnValue(of({ data: [], meta: { total: 0 } }));

    fixture = TestBed.createComponent(ChatSidebar);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should toggle search modal visibility', (): void => {
    expect(component.isSearchModalOpen()).toBe(false);

    component.openSearchModal();
    expect(component.isSearchModalOpen()).toBe(true);

    component.closeSearchModal();
    expect(component.isSearchModalOpen()).toBe(false);
  });

  it('should sync search term when opening modal', (): void => {
    component.searchTerm.set('test query');
    component.openSearchModal();

    expect(component.searchQuery()).toBe('test query');
  });

  it('should clear search query when closing modal', (): void => {
    component.searchQuery.set('some search');
    component.closeSearchModal();

    expect(component.searchQuery()).toBe('');
  });
});
