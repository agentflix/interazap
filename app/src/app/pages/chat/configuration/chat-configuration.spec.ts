import { describe, it, expect, beforeEach, vi } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';
import { ChatConfigurationPage } from './chat-configuration';
import {
  ChatRoutingQueueService,
  type ChatRoutingQueue,
  type ChatRoutingQueueAgent,
} from '../services/chat-routing-queue.service';
import { UserService, type User } from '@core/services/user.service';
import { ToastService } from '@core/services/toast.service';
import { signal } from '@angular/core';

function buildQueue(): ChatRoutingQueue {
  return {
    id: 'queue-1',
    tenant_id: 'tenant-1',
    instance_id: null,
    name: 'Global',
    is_enabled: true,
    strategy: 'round_robin',
    max_open_tickets_per_agent: null,
    agents: [
      {
        id: 'agent-1',
        queue_id: 'queue-1',
        user_id: 'user-1',
        position: 1,
        last_assigned_at: null,
        is_active: true,
        created_at: '',
        updated_at: '',
      },
    ],
    created_at: '',
    updated_at: '',
  };
}

function buildServiceMock(): {
  queue: ReturnType<typeof signal<ChatRoutingQueue | null>>;
  agents: ReturnType<typeof signal<ChatRoutingQueueAgent[]>>;
  loading: ReturnType<typeof signal<boolean>>;
  error: ReturnType<typeof signal<string | null>>;
  loadGlobal: ReturnType<typeof vi.fn>;
  save: ReturnType<typeof vi.fn>;
  addAgent: ReturnType<typeof vi.fn>;
  removeAgent: ReturnType<typeof vi.fn>;
  reorder: ReturnType<typeof vi.fn>;
} {
  return {
    queue: signal<ChatRoutingQueue | null>(buildQueue()),
    agents: signal<ChatRoutingQueueAgent[]>(buildQueue().agents),
    loading: signal(false),
    error: signal(null),
    loadGlobal: vi.fn(),
    save: vi.fn(),
    addAgent: vi.fn(),
    removeAgent: vi.fn(),
    reorder: vi.fn(),
  };
}

function buildUserServiceMock(): {
  list: ReturnType<typeof vi.fn>;
} {
  return {
    list: vi.fn().mockReturnValue(
      of({
        data: [{ id: 'user-2', name: 'Maria', email: 'maria@test.com', is_active: true } as User],
      }),
    ),
  };
}

describe('ChatConfigurationPage', () => {
  let fixture: ComponentFixture<ChatConfigurationPage>;
  let component: ChatConfigurationPage;
  let serviceMock: ReturnType<typeof buildServiceMock>;
  let userServiceMock: ReturnType<typeof buildUserServiceMock>;
  const toastMock = { success: vi.fn(), error: vi.fn() };

  beforeEach(async () => {
    serviceMock = buildServiceMock();
    userServiceMock = buildUserServiceMock();

    await TestBed.configureTestingModule({
      imports: [ChatConfigurationPage],
      providers: [
        { provide: ChatRoutingQueueService, useValue: serviceMock },
        { provide: UserService, useValue: userServiceMock },
        { provide: ToastService, useValue: toastMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(ChatConfigurationPage);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('carrega fila global e usuários no init', () => {
    expect(serviceMock.loadGlobal).toHaveBeenCalled();
    expect(userServiceMock.list).toHaveBeenCalledWith({ is_active: true, per_page: 100 });
  });

  it('alterna is_enabled e chama save', () => {
    const view = component as unknown as { toggleEnabled: () => void };
    view.toggleEnabled();
    expect(serviceMock.save).toHaveBeenCalledWith('global', { is_enabled: false });
  });

  it('não alterna quando fila é nula', () => {
    serviceMock.queue.set(null);
    const view = component as unknown as { toggleEnabled: () => void };
    view.toggleEnabled();
    expect(serviceMock.save).not.toHaveBeenCalled();
  });

  it('muda estratégia e chama save', () => {
    const view = component as unknown as { onStrategyChange: (s: string) => void };
    view.onStrategyChange('round_robin');
    expect(serviceMock.save).toHaveBeenCalledWith('global', { strategy: 'round_robin' });
  });

  it('adiciona agente e fecha formulário', () => {
    const view = component as unknown as {
      onAddAgent: (userId: string, position?: number) => void;
    };
    view.onAddAgent('user-2', 2);
    expect(serviceMock.addAgent).toHaveBeenCalledWith('global', 'user-2', 2);
    expect(component.showAddForm()).toBe(false);
  });

  it('remove agente', () => {
    const view = component as unknown as { onRemoveAgent: (userId: string) => void };
    view.onRemoveAgent('user-1');
    expect(serviceMock.removeAgent).toHaveBeenCalledWith('global', 'user-1');
  });

  it('reordena agentes', () => {
    const agents = buildQueue().agents;
    const view = component as unknown as { onReorder: (agents: ChatRoutingQueueAgent[]) => void };
    view.onReorder(agents);
    expect(serviceMock.reorder).toHaveBeenCalledWith('global', [
      { user_id: 'user-1', position: 1 },
    ]);
  });

  it('alterna ativo de agente e salva', () => {
    const view = component as unknown as {
      onToggleActive: (userId: string, isActive: boolean) => void;
    };
    view.onToggleActive('user-1', false);
    expect(serviceMock.save).toHaveBeenCalled();
    const savedArgs = serviceMock.save.mock.calls[0] as [
      'global',
      { agents: ChatRoutingQueueAgent[] },
    ];
    expect(savedArgs[1].agents[0].is_active).toBe(false);
  });

  it('exibe toast de erro quando falha ao carregar usuários', () => {
    userServiceMock.list.mockReturnValueOnce(throwError(() => new Error('fail')));

    const view = component as unknown as { loadUsers: () => void };
    view.loadUsers();
    fixture.detectChanges();

    expect(toastMock.error).toHaveBeenCalledWith('Erro ao carregar usuários.');
  });
});
