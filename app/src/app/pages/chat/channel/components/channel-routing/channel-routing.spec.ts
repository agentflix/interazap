import { describe, it, expect, beforeEach, vi } from 'vitest';
import { signal } from '@angular/core';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { ChannelRoutingComponent } from './channel-routing';
import {
  ChatRoutingQueueService,
  type ChatRoutingQueue,
  type ChatRoutingQueueAgent,
} from '../../../services/chat-routing-queue.service';
import { UserService } from '@core/services/user.service';
import { type User } from '@core/models/user.model';

describe('ChannelRoutingComponent', () => {
  let fixture: ComponentFixture<ChannelRoutingComponent>;
  let component: ChannelRoutingComponent;

  const mockQueue: ChatRoutingQueue = {
    id: 'queue-1',
    tenant_id: 'tenant-1',
    instance_id: null,
    name: 'Canal Teste',
    is_enabled: true,
    strategy: 'round_robin',
    max_open_tickets_per_agent: null,
    agents: [
      {
        id: 'a1',
        queue_id: 'queue-1',
        user_id: 'user-1',
        position: 1,
        last_assigned_at: null,
        is_active: true,
        skills: [],
        created_at: '',
        updated_at: '',
      },
    ],
    created_at: '',
    updated_at: '',
  };

  const mockUsers: User[] = [
    { id: 'user-1', name: 'Agente Um', email: 'a1@test.com', is_active: true },
    { id: 'user-2', name: 'Agente Dois', email: 'a2@test.com', is_active: true },
  ];

  const serviceMock = {
    queue: signal<ChatRoutingQueue | null>(mockQueue),
    agents: signal<ChatRoutingQueueAgent[]>(mockQueue.agents),
    loading: signal(false),
    error: signal<string | null>(null),
    loadForChannel: vi.fn(),
    save: vi.fn(),
    addAgent: vi.fn(),
    removeAgent: vi.fn(),
    reorder: vi.fn(),
    addAgentSkill: vi.fn(),
    removeAgentSkill: vi.fn(),
  };

  const userServiceMock = {
    list: vi.fn().mockReturnValue(of({ data: mockUsers })),
  };

  beforeEach(async () => {
    serviceMock.queue.set(mockQueue);
    serviceMock.agents.set(mockQueue.agents);
    serviceMock.loading.set(false);
    serviceMock.error.set(null);
    serviceMock.save.mockClear();
    serviceMock.loadForChannel.mockClear();
    serviceMock.addAgent.mockClear();
    serviceMock.removeAgent.mockClear();
    serviceMock.reorder.mockClear();
    userServiceMock.list.mockClear();

    await TestBed.configureTestingModule({
      imports: [ChannelRoutingComponent],
      providers: [
        { provide: ChatRoutingQueueService, useValue: serviceMock },
        { provide: UserService, useValue: userServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(ChannelRoutingComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('channelId', 'channel-1');
    fixture.componentRef.setInput('channelName', 'Canal Teste');
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('calls loadForChannel on init', () => {
    expect(serviceMock.loadForChannel).toHaveBeenCalledWith('channel-1');
  });

  it('loads users on init', () => {
    expect(userServiceMock.list).toHaveBeenCalledWith({ is_active: true, per_page: 100 });
    expect(component.users()).toEqual(mockUsers);
  });

  it('shows override fields when override is enabled', () => {
    component.overrideEnabled.set(true);
    fixture.detectChanges();

    const html = fixture.nativeElement.innerHTML;
    expect(html).toContain('Configuração da fila');
    expect(html).toContain('Agentes da fila');
  });

  it('renderiza agentes em ordem de position', () => {
    const orderedAgents: ChatRoutingQueueAgent[] = [
      { ...mockQueue.agents[0], id: 'a1', user_id: 'user-1', position: 1 },
      { ...mockQueue.agents[0], id: 'a2', user_id: 'user-2', position: 2 },
    ];
    serviceMock.agents.set(orderedAgents);
    fixture.detectChanges();

    const agentLabels = Array.from(
      fixture.nativeElement.querySelectorAll('app-routing-agent-list p.text-sm.font-medium') as NodeListOf<HTMLElement>,
    ).map((el) => el.textContent?.trim());

    expect(agentLabels).toEqual(['Agente #1', 'Agente #2']);
  });

  it('shows global badge when override is disabled', () => {
    component.overrideEnabled.set(false);
    fixture.detectChanges();

    const html = fixture.nativeElement.innerHTML;
    expect(html).toContain('Usando configuração global');
    expect(html).not.toContain('Configuração da fila');
  });

  it('emits closed when onClose is called', () => {
    const spy = vi.fn();
    component.closed.subscribe(spy);
    component.onClose();
    expect(spy).toHaveBeenCalled();
  });

  it('saves channel configuration when override is enabled', () => {
    component.overrideEnabled.set(true);
    component.isEnabledLocal.set(true);
    component.strategyControl.setValue('round_robin');

    component.onSave();

    expect(serviceMock.save).toHaveBeenCalledWith(
      'channel',
      expect.objectContaining({
        is_enabled: true,
        strategy: 'round_robin',
      }),
      'channel-1',
    );
  });

  it('saves least_busy strategy with max_open_tickets_per_agent', () => {
    component.overrideEnabled.set(true);
    component.isEnabledLocal.set(true);
    component.strategyControl.setValue('least_busy');
    component.maxOpenTicketsControl.setValue(5);

    component.onSave();

    expect(serviceMock.save).toHaveBeenCalledWith(
      'channel',
      expect.objectContaining({
        is_enabled: true,
        strategy: 'least_busy',
        max_open_tickets_per_agent: 5,
      }),
      'channel-1',
    );
  });

  it('syncs max_open_tickets_per_agent when queue loads with least_busy', () => {
    serviceMock.queue.set({ ...mockQueue, strategy: 'least_busy', max_open_tickets_per_agent: 3 });
    fixture.detectChanges();

    expect(component.strategyControl.value).toBe('least_busy');
    expect(component.maxOpenTicketsControl.value).toBe(3);
  });

  it('saves disabled state when override is turned off and queue exists', () => {
    component.overrideEnabled.set(false);
    serviceMock.queue.set(mockQueue);
    fixture.detectChanges();

    component.onSave();

    expect(serviceMock.save).toHaveBeenCalledWith(
      'channel',
      expect.objectContaining({ is_enabled: false }),
      'channel-1',
    );
  });

  it('does not call save when override is off and queue is null', () => {
    serviceMock.queue.set(null);
    component.overrideEnabled.set(false);
    fixture.detectChanges();

    component.onSave();

    expect(serviceMock.save).not.toHaveBeenCalled();
  });

  it('adds agent via service', () => {
    component.onAddAgent('user-2', 2);
    expect(serviceMock.addAgent).toHaveBeenCalledWith('channel', 'user-2', 2, 'channel-1');
    expect(component.showAddForm()).toBe(false);
  });

  it('removes agent via service', () => {
    component.onRemoveAgent('user-1');
    expect(serviceMock.removeAgent).toHaveBeenCalledWith('channel', 'user-1', 'channel-1');
  });

  it('reorders agents via service', () => {
    const agents: ChatRoutingQueueAgent[] = [
      { ...mockQueue.agents[0], position: 2 },
      {
        id: 'a2',
        queue_id: 'queue-1',
        user_id: 'user-2',
        position: 1,
        last_assigned_at: null,
        is_active: true,
        skills: [],
        created_at: '',
        updated_at: '',
      },
    ];

    component.onReorder(agents);

    expect(serviceMock.reorder).toHaveBeenCalledWith(
      'channel',
      [
        { user_id: 'user-1', position: 1 },
        { user_id: 'user-2', position: 2 },
      ],
      'channel-1',
    );
  });

  it('adiciona skill a agente do canal', () => {
    component.onAddSkill('user-1', 'suporte_tecnico');
    expect(serviceMock.addAgentSkill).toHaveBeenCalledWith('channel', 'user-1', 'suporte_tecnico', 'channel-1');
  });

  it('remove skill de agente do canal', () => {
    component.onRemoveSkill('user-1', 'suporte_tecnico');
    expect(serviceMock.removeAgentSkill).toHaveBeenCalledWith('channel', 'user-1', 'suporte_tecnico', 'channel-1');
  });

  it('toggles local enabled state without persisting', () => {
    component.isEnabledLocal.set(false);
    component.toggleEnabled();
    expect(component.isEnabledLocal()).toBe(true);
    expect(serviceMock.save).not.toHaveBeenCalled();
  });
});
