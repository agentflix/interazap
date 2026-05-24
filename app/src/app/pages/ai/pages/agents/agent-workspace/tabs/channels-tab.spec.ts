import { TestBed, type ComponentFixture } from '@angular/core/testing';
import { describe, expect, it, beforeEach, vi } from 'vitest';
import { of } from 'rxjs';
import { AgentChannelsTabComponent } from './channels-tab';
import { AiAgentService } from '@ai/services/ai-agent.service';
import { ToastService } from '@core/services/toast.service';

describe('AgentChannelsTabComponent', () => {
  let component: AgentChannelsTabComponent;
  let fixture: ComponentFixture<AgentChannelsTabComponent>;

  const agentServiceMock = {
    update: vi.fn().mockReturnValue(of({})),
  };

  const toastServiceMock = {
    success: vi.fn(),
    error: vi.fn(),
  };

  beforeEach(async () => {
    vi.clearAllMocks();

    await TestBed.configureTestingModule({
      imports: [AgentChannelsTabComponent],
      providers: [
        { provide: AiAgentService, useValue: agentServiceMock },
        { provide: ToastService, useValue: toastServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(AgentChannelsTabComponent);
    component = fixture.componentInstance;
  });

  it('should default to whatsapp=true when agent has no channels', () => {
    fixture.componentRef.setInput('agent', {
      id: '123',
      name: 'Test',
      is_active: true,
      channels: [],
      role: 'support_l1',
    });
    fixture.detectChanges();

    expect(component.enabledChannels()).toContain('whatsapp');
    expect(component.enabledChannels()).not.toContain('email');
    expect(component.enabledChannels()).not.toContain('internal');
    expect(component.enabledChannels()).not.toContain('webchat');
  });

  it('should NOT have an individual save button in the template', () => {
    fixture.componentRef.setInput('agent', {
      id: '123',
      name: 'Test',
      is_active: true,
      channels: [],
      role: 'support_l1',
    });
    fixture.detectChanges();

    const el = fixture.nativeElement as HTMLElement;
    const saveButtons = el.querySelectorAll('af-loading-button');
    expect(saveButtons.length).toBe(0);
  });
});
