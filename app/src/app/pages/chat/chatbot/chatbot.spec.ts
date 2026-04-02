import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { Chatbot } from './chatbot';
import { ChatbotRuleService } from '@core/services/chatbot-rule.service';
import { DepartmentService } from '@core/services/department.service';

class ChatbotRuleServiceStub {
  list = vi.fn().mockReturnValue(of({ success: true, data: { data: [] } }));

  create = vi.fn().mockReturnValue(
    of({
      success: true,
      data: {
        id: 'rule-1',
        company_id: 'company-1',
        name: 'Regra menu',
        match_type: 'contains',
        patterns: ['menu'],
        actions: [{ type: 'send_message', message: 'Olá!', message_type: '1' }],
        cooldown_seconds: 60,
        priority: 0,
        is_active: true,
        respect_business_hours: true,
        is_welcome: true,
        created_at: '2026-01-21 00:00:00',
        updated_at: '2026-01-21 00:00:00',
      },
    }),
  );

  update = vi.fn().mockReturnValue(of({ success: true, data: { id: 'rule-1' } }));
  validateKeyword = vi.fn().mockReturnValue(of({ success: true, data: { available: true } }));
  delete = vi.fn().mockReturnValue(of(void 0));
  toggle = vi.fn().mockReturnValue(of({ data: { id: 'rule-1' } }));
}

class DepartmentServiceStub {
  list = vi.fn().mockReturnValue(of({ data: [] }));
}

describe('Chatbot', () => {
  let component: Chatbot;
  let chatbotRules: ChatbotRuleServiceStub;
  let departments: DepartmentServiceStub;

  beforeEach((): void => {
    TestBed.configureTestingModule({
      providers: [
        { provide: ChatbotRuleService, useClass: ChatbotRuleServiceStub },
        { provide: DepartmentService, useClass: DepartmentServiceStub },
      ],
    });

    chatbotRules = TestBed.inject(ChatbotRuleService) as unknown as ChatbotRuleServiceStub;
    departments = TestBed.inject(DepartmentService) as unknown as DepartmentServiceStub;
    component = TestBed.runInInjectionContext(() => new Chatbot());
    component.ngOnInit();
  });

  it('should list chatbot rules and departments on init', () => {
    expect(chatbotRules.list).toHaveBeenCalled();
    expect(departments.list).toHaveBeenCalled();
  });

  it('opens create modal with reset form state', () => {
    component.openCreate();

    expect(component.isEditOpen()).toBe(true);
    expect(component.editingRuleId()).toBeNull();
    expect(component.form.controls.keyword.value).toBe('');
  });

  it('saves new rule with is_welcome payload', () => {
    component.openCreate();
    component.form.patchValue({
      keyword: 'menu',
      caption: 'Olá!',
      actionType: 'message',
      isWelcome: true,
    });
    component.keywordStatus.set('available');

    component.saveEdit();

    expect(chatbotRules.create).toHaveBeenCalled();
    const payload = chatbotRules.create.mock.calls.at(-1)?.[0] as { is_welcome?: boolean };
    expect(payload.is_welcome).toBe(true);
  });

  it('validates keyword with debounce', () => {
    vi.useFakeTimers();

    component.form.controls.keyword.setValue('menu');
    vi.advanceTimersByTime(450);

    expect(chatbotRules.validateKeyword).toHaveBeenCalledWith({
      keyword: 'menu',
      match_type: 'contains',
      instance_id: null,
      department_id: null,
      rule_id: null,
    });

    vi.useRealTimers();
  });

  it('opens delete confirmation and removes rule', () => {
    const rule = {
      id: 'rule-9',
      name: 'Excluir',
      patterns: ['excluir'],
      is_welcome: false,
      company_id: 'company-1',
      match_type: 'contains' as const,
      actions: [],
      cooldown_seconds: 60,
      priority: 0,
      is_active: true,
      respect_business_hours: true,
      created_at: '2026-01-30',
      updated_at: '2026-01-30',
    };

    component.rules.set([rule]);
    component.openDelete(rule);
    component.confirmDelete();

    expect(chatbotRules.delete).toHaveBeenCalledWith('rule-9');
    expect(component.rules()).toEqual([]);
  });

  it('toggles rule status', () => {
    const rule = {
      id: 'rule-1',
      name: 'Menu',
      patterns: ['menu'],
      is_welcome: false,
      company_id: 'company-1',
      match_type: 'contains' as const,
      actions: [],
      cooldown_seconds: 60,
      priority: 0,
      is_active: true,
      respect_business_hours: true,
      created_at: '2026-01-30',
      updated_at: '2026-01-30',
    };

    component.toggleRule(rule);

    expect(chatbotRules.toggle).toHaveBeenCalledWith('rule-1');
  });
});
