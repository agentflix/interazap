import { TestBed, type ComponentFixture } from '@angular/core/testing';
import { importProvidersFrom, signal } from '@angular/core';
import { provideRouter } from '@angular/router';
import { LucideAngularModule, icons } from 'lucide-angular';
import { of } from 'rxjs';
import { describe, expect, it, beforeEach, vi } from 'vitest';
import { AiSimulatorComponent } from './simulator';
import { AiAgentService } from '@ai/services/ai-agent.service';
import { AutopilotRunService } from '@ai/services/autopilot-run.service';
import { AiRealtimeService } from '@ai/services/ai-realtime.service';
import { ToastService } from '../../../core/services/toast.service';

interface RealtimeLastEventState {
  started: null;
  thinking: null;
  toolCall: null;
  toolResult: null;
  completed: {
    run_id: string;
    output: { response: string; raw: { audio_url: string } };
  } | null;
  failed: null;
  blocked: null;
  streaming: null;
  type: string | null;
  version: number;
}

interface RealtimeStub {
  connected: ReturnType<typeof signal<boolean>>;
  lastEvent: ReturnType<typeof signal<RealtimeLastEventState>>;
  connect: ReturnType<typeof vi.fn>;
  realtime: {
    joinRooms: ReturnType<typeof vi.fn>;
    startSlowNetworkMonitor: ReturnType<typeof vi.fn>;
  };
  emitEvent: (event: {
    completed: { run_id: string; output: { response: string; raw: { audio_url: string } } };
    type: string;
    version: number;
  }) => void;
}

function createRealtimeStub(): RealtimeStub {
  const lastEventSignal = signal<RealtimeLastEventState>({
    started: null,
    thinking: null,
    toolCall: null,
    toolResult: null,
    completed: null,
    failed: null,
    blocked: null,
    streaming: null,
    type: null,
    version: 0,
  });

  return {
    connected: signal(true),
    lastEvent: lastEventSignal,
    connect: vi.fn(),
    realtime: {
      joinRooms: vi.fn(),
      startSlowNetworkMonitor: vi.fn(),
    },
    emitEvent(event: {
      completed: { run_id: string; output: { response: string; raw: { audio_url: string } } };
      type: string;
      version: number;
    }) {
      lastEventSignal.set({
        started: null,
        thinking: null,
        toolCall: null,
        toolResult: null,
        completed: event.completed,
        failed: null,
        blocked: null,
        streaming: null,
        type: event.type,
        version: event.version,
      });
    },
  };
}

function setupDefaultMocks(
  agentServiceMock: { list: ReturnType<typeof vi.fn>; simulate: ReturnType<typeof vi.fn> },
  runServiceMock: {
    create: ReturnType<typeof vi.fn>;
    list: ReturnType<typeof vi.fn>;
    get: ReturnType<typeof vi.fn>;
    cancel: ReturnType<typeof vi.fn>;
  },
): void {
  agentServiceMock.list.mockReturnValue(
    of({
      data: [
        {
          id: 'agent-1',
          name: 'Agent One',
          type: 'support',
          role: 'support_l1',
          max_tokens: 2048,
          temperature: 0.7,
          top_p: 1,
          is_active: true,
        },
      ],
    }),
  );

  agentServiceMock.simulate.mockReturnValue(
    of({ id: 'run-1', playbook_id: 'pb-1', status: 'queued', playbook_version: 1 }),
  );

  runServiceMock.list.mockReturnValue(of({ data: [], meta: { last_page: 1 } }));
  runServiceMock.create.mockReturnValue(
    of({ id: 'run-1', playbook_id: 'pb-1', status: 'queued', playbook_version: 1 }),
  );
  runServiceMock.get.mockReturnValue(
    of({ id: 'run-1', playbook_id: 'pb-1', status: 'completed', playbook_version: 1 }),
  );
  runServiceMock.cancel.mockReturnValue(
    of({ id: 'run-1', playbook_id: 'pb-1', status: 'failed', playbook_version: 1 }),
  );
}

describe('AiSimulatorComponent', () => {
  let fixture: ComponentFixture<AiSimulatorComponent>;
  const realtimeStub = createRealtimeStub();

  const agentServiceMock = {
    list: vi.fn(),
    simulate: vi.fn(),
  };

  const runServiceMock = {
    create: vi.fn(),
    list: vi.fn(),
    get: vi.fn(),
    cancel: vi.fn(),
  };

  const toastMock = {
    success: vi.fn(),
    error: vi.fn(),
  };

  beforeEach(async () => {
    setupDefaultMocks(agentServiceMock, runServiceMock);

    await TestBed.configureTestingModule({
      imports: [AiSimulatorComponent],
      providers: [
        provideRouter([]),
        importProvidersFrom(LucideAngularModule.pick(icons)),
        { provide: AiAgentService, useValue: agentServiceMock },
        { provide: AutopilotRunService, useValue: runServiceMock },
        { provide: AiRealtimeService, useValue: realtimeStub },
        { provide: ToastService, useValue: toastMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(AiSimulatorComponent);
  });

  it('attaches audio url to assistant message on completed event', () => {
    fixture.detectChanges();

    const component = fixture.componentInstance;
    component.form.controls.agent_id.setValue('agent-1');
    component.form.controls.message.setValue('Olá');
    component.sendChatMessage();

    realtimeStub.emitEvent({
      completed: {
        run_id: 'run-1',
        output: {
          response: 'Resposta com áudio',
          raw: { audio_url: 'https://cdn.test/voice.mp3' },
        },
      },
      type: 'ai.run.completed',
      version: 1,
    });

    TestBed.flushEffects();
    fixture.detectChanges();

    const lastMessage = component.chatMessages().at(-1);
    expect(lastMessage?.audioUrl).toBe('https://cdn.test/voice.mp3');
    expect(lastMessage?.text).toBe('Resposta com áudio');
  });
});
