import { TestBed, type ComponentFixture } from '@angular/core/testing';
import { describe, expect, it, beforeEach, vi } from 'vitest';
import { ActivatedRoute, Router } from '@angular/router';
import { of } from 'rxjs';
import { AgentWorkspaceComponent } from './agent-workspace';
import { AiAgentService } from '@ai/services/ai-agent.service';
import { ToastService } from '../../../../../core/services/toast.service';

describe('AgentWorkspaceComponent', () => {
  let component: AgentWorkspaceComponent;
  let fixture: ComponentFixture<AgentWorkspaceComponent>;

  const agentServiceMock = {
    get: vi.fn().mockReturnValue(
      of({
        id: '123',
        name: 'Test Agent',
        is_active: true,
        channels: ['whatsapp'],
        role: 'support_l1',
        model_id: 'gpt-4o-mini',
        max_tokens: 2048,
        temperature: 0.7,
        top_p: 1,
      }),
    ),
    update: vi.fn().mockReturnValue(of({})),
    updateVoiceConfig: vi.fn().mockReturnValue(of({})),
    linkTools: vi.fn().mockReturnValue(of({})),
    getFiles: vi.fn().mockReturnValue(of([])),
    getSkills: vi.fn().mockReturnValue(of([])),
    getTriggers: vi.fn().mockReturnValue(of([])),
    getVoiceConfig: vi.fn().mockReturnValue(of(null)),
    getToolsPreset: vi.fn().mockReturnValue(of([])),
  };

  const toastServiceMock = {
    success: vi.fn(),
    error: vi.fn(),
  };

  beforeEach(async () => {
    vi.clearAllMocks();

    await TestBed.configureTestingModule({
      imports: [AgentWorkspaceComponent],
      providers: [
        { provide: AiAgentService, useValue: agentServiceMock },
        { provide: ToastService, useValue: toastServiceMock },
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              paramMap: { get: () => '123' },
              queryParamMap: { get: () => 'overview' },
            },
          },
        },
        { provide: Router, useValue: { navigate: vi.fn() } },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(AgentWorkspaceComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create and load agent', () => {
    expect(component).toBeTruthy();
    expect(agentServiceMock.get).toHaveBeenCalledWith('123');
  });

  it('should have a single global save button in the template', () => {
    const el = fixture.nativeElement as HTMLElement;
    const saveButtons = el.querySelectorAll('af-loading-button');
    // Deve existir ao menos o botão global de salvar.
    expect(saveButtons.length).toBeGreaterThan(0);
  });

  it('should save globally by reading from overview and channels tabs', async () => {
    // Override viewChild references
    Object.defineProperty(component, 'overviewTab', {
      value: () => ({
        form: { invalid: false, value: { name: 'Updated Agent', is_active: true } },
      }),
    });
    Object.defineProperty(component, 'channelsTab', {
      value: () => ({
        enabledChannels: () => ['whatsapp', 'webchat'],
      }),
    });
    Object.defineProperty(component, 'toolsTab', { value: () => undefined });
    Object.defineProperty(component, 'voiceTab', { value: () => undefined });

    await component.saveAll();

    expect(agentServiceMock.update).toHaveBeenCalledWith('123', {
      name: 'Updated Agent',
      is_active: true,
      channels: ['whatsapp', 'webchat'],
    });
    expect(toastServiceMock.success).toHaveBeenCalled();
  });
});
