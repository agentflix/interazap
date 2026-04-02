import { TestBed, type ComponentFixture } from '@angular/core/testing';
import { describe, expect, it, beforeEach, vi } from 'vitest';
import { AgentOverviewTabComponent } from './overview-tab';

describe('AgentOverviewTabComponent', () => {
  let component: AgentOverviewTabComponent;
  let fixture: ComponentFixture<AgentOverviewTabComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AgentOverviewTabComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(AgentOverviewTabComponent);
    component = fixture.componentInstance;

    fixture.componentRef.setInput('agent', {
      id: '123',
      name: 'Test Agent',
      is_active: true,
      channels: ['whatsapp'],
      role: 'support_l1',
      model_id: 'gpt-4o-mini',
      max_tokens: 2048,
      temperature: 0.7,
      top_p: 1,
      fallback_message: '',
    });

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

  it('should expose form for global save to read', () => {
    expect(component.form).toBeDefined();
    expect(component.form.controls.name).toBeDefined();
    expect(component.form.controls.model_id).toBeDefined();
  });

  it('should NOT have emoji-related rendering', () => {
    const el = fixture.nativeElement as HTMLElement;
    const emojiSpan = el.querySelector('.text-lg');
    expect(emojiSpan).toBeNull();
  });
});
