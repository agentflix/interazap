import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { AutoReply } from './auto-reply';
import { AutoReplyService } from '@core/services/auto-reply.service';
import { DepartmentService } from '@core/services/department.service';

class AutoReplyServiceStub {
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

describe('AutoReply', () => {
  let component: AutoReply;
  let autoReplyRules: AutoReplyServiceStub;
  let departments: DepartmentServiceStub;

  beforeEach((): void => {
    TestBed.configureTestingModule({
      providers: [
        { provide: AutoReplyService, useClass: AutoReplyServiceStub },
        { provide: DepartmentService, useClass: DepartmentServiceStub },
      ],
    });

    autoReplyRules = TestBed.inject(AutoReplyService) as unknown as AutoReplyServiceStub;
    departments = TestBed.inject(DepartmentService) as unknown as DepartmentServiceStub;
    component = TestBed.runInInjectionContext(() => new AutoReply());
    component.ngOnInit();
  });

  it('should list auto reply rules and departments on init', () => {
    expect(autoReplyRules.list).toHaveBeenCalled();
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

    expect(autoReplyRules.create).toHaveBeenCalled();
    const payload = autoReplyRules.create.mock.calls.at(-1)?.[0] as { is_welcome?: boolean };
    expect(payload.is_welcome).toBe(true);
  });

  it('validates keyword with debounce', () => {
    vi.useFakeTimers();

    component.form.controls.keyword.setValue('menu');
    vi.advanceTimersByTime(450);

    expect(autoReplyRules.validateKeyword).toHaveBeenCalledWith({
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

    expect(autoReplyRules.delete).toHaveBeenCalledWith('rule-9');
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

    expect(autoReplyRules.toggle).toHaveBeenCalledWith('rule-1');
  });
});
