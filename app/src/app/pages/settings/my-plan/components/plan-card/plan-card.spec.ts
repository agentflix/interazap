import type { ComponentFixture} from '@angular/core/testing';
import { TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { PlanCardComponent } from './plan-card';
import { type SubscriptionPlan } from '@shared/models/subscription.model';

function buildPlan(overrides: Partial<SubscriptionPlan> = {}): SubscriptionPlan {
  return {
    id: 'plan-starter',
    name: 'Starter',
    slug: 'starter',
    price_monthly: '97.00',
    limit_users: 5,
    whatsapp_integrations_limit: 1,
    storage_mode: 'LIMITED' as const,
    storage_limit_bytes: 1024 * 1024 * 1024,
    ai_enabled: false,
    negotiations_mode: 'LIMITED' as const,
    negotiations_limit: 50,
    is_current: true,
    features: [
      { label: '5 usuários', included: true },
      { label: '1 canal WhatsApp', included: true },
      { label: '1.0 GB de storage', included: true },
      { label: 'Chatbot (respostas automáticas)', included: true },
      { label: 'Inteligência Artificial', included: false },
      { label: 'Negociações ilimitadas', included: true },
    ],
    ...overrides,
  };
}

describe('PlanCardComponent', () => {
  let fixture: ComponentFixture<PlanCardComponent>;
  let component: PlanCardComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PlanCardComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(PlanCardComponent);
    component = fixture.componentInstance;
  });

  it('deve renderizar features inclusas com ícone de check e texto visível', () => {
    fixture.componentRef.setInput('plan', buildPlan());
    fixture.componentRef.setInput('currentPlanPrice', 97);
    fixture.detectChanges();

    const includedFeatures = fixture.debugElement.queryAll(
      By.css('li.text-neutral-700'),
    );

    expect(includedFeatures.length).toBeGreaterThan(0);
    expect(includedFeatures[0].nativeElement.textContent).toContain('✓');
    expect(includedFeatures[0].nativeElement.textContent).toContain('5 usuários');
  });

  it('deve renderizar features não-inclusas com ícone de X, texto riscado e cor apagada', () => {
    fixture.componentRef.setInput('plan', buildPlan());
    fixture.componentRef.setInput('currentPlanPrice', 97);
    fixture.detectChanges();

    const excludedFeature = fixture.debugElement.query(
      By.css('li.line-through'),
    )?.nativeElement as HTMLElement | undefined;

    expect(excludedFeature).toBeTruthy();
    expect(excludedFeature?.className).toContain('line-through');
    expect(excludedFeature?.textContent).toContain('✕');
    expect(excludedFeature?.textContent).toContain('Inteligência Artificial');
  });

  it('deve mostrar badge "Plano Atual" quando is_current=true', () => {
    fixture.componentRef.setInput('plan', buildPlan({ is_current: true }));
    fixture.componentRef.setInput('currentPlanPrice', 97);
    fixture.detectChanges();

    const badge = fixture.debugElement.query(
      By.css('af-status-badge'),
    )?.nativeElement as HTMLElement | undefined;

    expect(badge).toBeTruthy();
    expect(badge?.textContent).toContain('Plano Atual');
  });

  it('deve mostrar botão desabilitado "Plano atual" quando is_current=true', () => {
    fixture.componentRef.setInput('plan', buildPlan({ is_current: true }));
    fixture.componentRef.setInput('currentPlanPrice', 97);
    fixture.detectChanges();

    const button = fixture.debugElement.query(
      By.css('af-button[disabled]'),
    )?.nativeElement as HTMLElement | undefined;

    expect(button).toBeTruthy();
    expect(button?.textContent).toContain('Plano atual');
  });

  it('deve mostrar botão "Upgrade" quando plano selecionado é mais caro', () => {
    fixture.componentRef.setInput('plan', buildPlan({ is_current: false, price_monthly: '297.00' }));
    fixture.componentRef.setInput('currentPlanPrice', 97);
    fixture.detectChanges();

    const button = fixture.debugElement.query(
      By.css('af-button:not([disabled])'),
    )?.nativeElement as HTMLElement | undefined;

    expect(button).toBeTruthy();
    expect(button?.textContent).toContain('Upgrade');
  });

  it('deve mostrar botão "Downgrade" quando plano selecionado é mais barato', () => {
    fixture.componentRef.setInput('plan', buildPlan({ is_current: false, price_monthly: '47.00' }));
    fixture.componentRef.setInput('currentPlanPrice', 97);
    fixture.detectChanges();

    const button = fixture.debugElement.query(
      By.css('af-button:not([disabled])'),
    )?.nativeElement as HTMLElement | undefined;

    expect(button).toBeTruthy();
    expect(button?.textContent).toContain('Downgrade');
  });

  it('deve emitir evento selected ao clicar no botão de ação', () => {
    const selectedSpy = vi.fn();
    const plan = buildPlan({ is_current: false, price_monthly: '297.00' });

    fixture.componentRef.setInput('plan', plan);
    fixture.componentRef.setInput('currentPlanPrice', 97);
    fixture.detectChanges();

    component.selected.subscribe(selectedSpy);

    const button = fixture.debugElement.query(
      By.css('af-button:not([disabled])'),
    );
    button.triggerEventHandler('click', null);

    expect(selectedSpy).toHaveBeenCalledWith(plan);
  });
});
