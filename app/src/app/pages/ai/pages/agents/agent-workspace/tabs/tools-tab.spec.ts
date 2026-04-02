import { TestBed, type ComponentFixture } from '@angular/core/testing';
import { describe, expect, it, beforeEach, vi } from 'vitest';
import { of } from 'rxjs';
import { AgentToolsTabComponent } from './tools-tab';
import { AiAgentService } from '@ai/services/ai-agent.service';
import { ToastService } from '@core/services/toast.service';

describe('AgentToolsTabComponent', () => {
  let component: AgentToolsTabComponent;
  let fixture: ComponentFixture<AgentToolsTabComponent>;

  const aiAgentServiceMock = {
    getToolsCatalog: vi.fn().mockReturnValue(of([])),
    getAgentTools: vi.fn().mockReturnValue(of([])),
    getToolsPreset: vi.fn().mockReturnValue(of(['send_message', 'close_ticket'])),
    updateAgentTools: vi.fn().mockReturnValue(of({})),
  };

  const toastServiceMock = {
    success: vi.fn(),
    error: vi.fn(),
  };

  beforeEach(async () => {
    vi.clearAllMocks();

    await TestBed.configureTestingModule({
      imports: [AgentToolsTabComponent],
      providers: [
        { provide: AiAgentService, useValue: aiAgentServiceMock },
        { provide: ToastService, useValue: toastServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(AgentToolsTabComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('agentId', 'uuid');
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should NOT have an individual save button in the template', () => {
    const el = fixture.nativeElement as HTMLElement;
    const saveButtons = el.querySelectorAll('af-loading-button');
    expect(saveButtons.length).toBe(0);
  });

  it('should call getToolsPreset when applying a preset role', () => {
    component.applyPreset('support_l1');
    expect(aiAgentServiceMock.getToolsPreset).toHaveBeenCalledWith('support_l1');
  });

  it('should merge preset tools into linkedToolNames after applyPreset', () => {
    component.applyPreset('support_l1');
    fixture.detectChanges();

    // The mock returns ['send_message', 'close_ticket']
    expect(component.linkedToolNames()).toContain('send_message');
    expect(component.linkedToolNames()).toContain('close_ticket');
  });

  it('should render the preset select input in the template', () => {
    const el = fixture.nativeElement as HTMLElement;
    const selectInputs = el.querySelectorAll('af-select-input');
    expect(selectInputs.length).toBeGreaterThanOrEqual(1);
  });

  it('should have roleOptions with at least the standard roles', () => {
    expect(component.roleOptions.length).toBeGreaterThan(0);
    const values = component.roleOptions.map((o) => o.value);
    expect(values).toContain('sales_qualifier');
    expect(values).toContain('support_l1');
  });

  it('should keep linked tools when API returns tool link format', () => {
    aiAgentServiceMock.getToolsCatalog.mockReturnValueOnce(
      of([
        {
          id: 'send_message',
          name: 'send_message',
          description: 'Send message',
          category: 'chat',
          execution_mode: 'local',
          requires_approval: false,
          is_active: true,
          permissions: ['general'],
        },
      ]),
    );
    aiAgentServiceMock.getAgentTools.mockReturnValueOnce(
      of([{ tool_id: 'send_message', tool_name: 'send_message' }]),
    );

    component.loadData();

    expect(component.linkedToolNames()).toEqual(['send_message']);
  });

  it('should keep linked tools when API returns legacy catalog format', () => {
    aiAgentServiceMock.getToolsCatalog.mockReturnValueOnce(
      of([
        {
          id: 'close_ticket',
          name: 'close_ticket',
          description: 'Close ticket',
          category: 'chat',
          execution_mode: 'local',
          requires_approval: false,
          is_active: true,
          permissions: ['general'],
        },
      ]),
    );
    aiAgentServiceMock.getAgentTools.mockReturnValueOnce(
      of([
        {
          name: 'close_ticket',
          display_name: 'Close Ticket',
          description: 'Close ticket',
          handler_class: 'Domain\\Ai\\Tools\\CloseTicketTool',
          is_active: true,
        },
      ]),
    );

    component.loadData();

    expect(component.linkedToolNames()).toEqual(['close_ticket']);
  });
});
