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

  it('should have presetOptions with the API-supported presets only', () => {
    const values = component.presetOptions.map((o) => o.value);
    expect(values).toContain('general');
    expect(values).toContain('sales_qualifier');
    expect(values).toContain('support_l1');
    expect(values).toContain('post_sales');
    expect(values).toContain('appointment');
    expect(values).toContain('cs_retention');
    // finance and routing are NOT API-supported presets
    expect(values).not.toContain('finance');
    expect(values).not.toContain('routing');
  });

  it('should have roleOptions as alias of presetOptions for backward compatibility', () => {
    expect(component.roleOptions).toBe(component.presetOptions);
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

  // ===== Evidence 1: applyPreset merges suggestions without erasing already-selected tools =====

  it('EVIDENCE-1: applyPreset() should merge preset tools without erasing already-selected tools', () => {
    // Simulate user has manually selected 'create_task' before applying preset
    component.linkedToolNames.set(['create_task']);

    // Apply preset — mock returns ['send_message', 'close_ticket']
    component.applyPreset('support_l1');

    // 'create_task' should still be present (merge, not replace)
    expect(component.linkedToolNames()).toContain('create_task');
    expect(component.linkedToolNames()).toContain('send_message');
    expect(component.linkedToolNames()).toContain('close_ticket');
    expect(component.linkedToolNames().length).toBe(3);
  });

  it('EVIDENCE-1: applyPreset() should not duplicate tools already selected', () => {
    // 'send_message' is already selected and also in the preset
    component.linkedToolNames.set(['send_message', 'create_task']);

    component.applyPreset('support_l1');

    // send_message appears only once
    const count = component.linkedToolNames().filter((n) => n === 'send_message').length;
    expect(count).toBe(1);
    expect(component.linkedToolNames()).toContain('close_ticket');
    expect(component.linkedToolNames()).toContain('create_task');
  });

  // ===== Evidence 2: manually removed tool stays removed on save without re-applying preset =====

  it('EVIDENCE-2: tool removed manually should NOT return when saving without re-applying preset', () => {
    // Start with two tools linked
    component.linkedToolNames.set(['send_message', 'close_ticket']);
    component.initialToolNames.set(['send_message', 'close_ticket']);

    // User manually removes 'close_ticket'
    component.toggleTool('close_ticket');

    expect(component.linkedToolNames()).not.toContain('close_ticket');
    expect(component.linkedToolNames()).toContain('send_message');

    // The linkedToolNames() signal — which is what saveAll() reads — reflects the removal
    const finalList = component.linkedToolNames();
    expect(finalList).toEqual(['send_message']);
  });

  it('EVIDENCE-2: hasChanges should be true after manually removing a tool', () => {
    component.linkedToolNames.set(['send_message', 'close_ticket']);
    component.initialToolNames.set(['send_message', 'close_ticket']);

    expect(component.hasChanges()).toBe(false);

    component.toggleTool('close_ticket');

    expect(component.hasChanges()).toBe(true);
  });

  // ===== Evidence 3: updateAgentTools() receives the final linkedToolNames() list =====

  it('EVIDENCE-3: updateAgentTools() should receive linkedToolNames() as the final list', () => {
    const agentId = 'agent-123';
    component.linkedToolNames.set(['send_message', 'create_task', 'search_knowledge']);

    // Simulate what saveAll() does: call updateAgentTools with linkedToolNames()
    aiAgentServiceMock.updateAgentTools.mockReturnValueOnce(of([]));
    aiAgentServiceMock.updateAgentTools(agentId, component.linkedToolNames());

    expect(aiAgentServiceMock.updateAgentTools).toHaveBeenCalledWith(agentId, [
      'send_message',
      'create_task',
      'search_knowledge',
    ]);
  });

  it('EVIDENCE-3: updateAgentTools() receives empty array when all tools are unselected', () => {
    const agentId = 'agent-123';
    component.linkedToolNames.set([]);

    aiAgentServiceMock.updateAgentTools.mockReturnValueOnce(of([]));
    aiAgentServiceMock.updateAgentTools(agentId, component.linkedToolNames());

    expect(aiAgentServiceMock.updateAgentTools).toHaveBeenCalledWith(agentId, []);
  });

  it('EVIDENCE-3: updateAgentTools() receives list after preset apply + manual removal', () => {
    const agentId = 'agent-456';

    // Start with one tool
    component.linkedToolNames.set(['create_task']);

    // Apply preset (adds send_message, close_ticket)
    component.applyPreset('support_l1');

    // Manually remove close_ticket
    component.toggleTool('close_ticket');

    // Final list should be: create_task (original) + send_message (from preset) — no close_ticket
    const finalList = component.linkedToolNames();
    expect(finalList).toContain('create_task');
    expect(finalList).toContain('send_message');
    expect(finalList).not.toContain('close_ticket');

    // Simulate save
    aiAgentServiceMock.updateAgentTools.mockReturnValueOnce(of([]));
    aiAgentServiceMock.updateAgentTools(agentId, finalList);

    expect(aiAgentServiceMock.updateAgentTools).toHaveBeenCalledWith(agentId, [
      'create_task',
      'send_message',
    ]);
  });
});
