import { TestBed, type ComponentFixture } from '@angular/core/testing';
import { describe, expect, it, beforeEach, vi } from 'vitest';
import { ReactiveFormsModule } from '@angular/forms';
import { of } from 'rxjs';
import { By } from '@angular/platform-browser';
import { AgentTriggersTabComponent } from './triggers-tab';
import { AiAgentService } from '@ai/services/ai-agent.service';
import { ToastService } from '@core/services/toast.service';

describe('AgentTriggersTabComponent', () => {
  let component: AgentTriggersTabComponent;
  let fixture: ComponentFixture<AgentTriggersTabComponent>;

  beforeEach(async () => {
    const aiAgentServiceMock = {
      updateTrigger: vi.fn(),
      createTrigger: vi.fn(),
      getTriggers: vi.fn().mockReturnValue(of([])),
    };
    const toastServiceMock = {
      success: vi.fn(),
      error: vi.fn(),
    };

    await TestBed.configureTestingModule({
      imports: [ReactiveFormsModule, AgentTriggersTabComponent],
      providers: [
        { provide: AiAgentService, useValue: aiAgentServiceMock },
        { provide: ToastService, useValue: toastServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(AgentTriggersTabComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('agentId', 'agent-123');
    fixture.detectChanges();
  });

  it('should have eventOptions populated with predefined events', () => {
    expect(component.eventOptions.length).toBeGreaterThan(0);
    expect(component.eventOptions[0].value).toBe('message.created');
  });

  it('should render af-select-input for event_name when trigger_type is event', () => {
    component.openCreateModal();
    fixture.detectChanges();

    component.triggerForm.controls.trigger_type.setValue('event');
    fixture.detectChanges();

    const selectInputs = fixture.debugElement.queryAll(By.css('af-select-input'));
    expect(selectInputs.length).toBeGreaterThan(0);
  });
});
