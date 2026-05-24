import { describe, it, expect, beforeEach } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { UsageStatsComponent } from './usage-stats';
import { type SubscriptionUsage } from '@shared/models/subscription.model';

function buildUsage(aiOverrides: Partial<SubscriptionUsage['ai_messages']> = {}): SubscriptionUsage {
  return {
    users: { current: 2, limit: 10, percentage: 20 },
    instances: { current: 1, limit: 3, percentage: 33 },
    storage: {
      used_bytes: 100,
      limit_bytes: 1024,
      used_formatted: '100 B',
      limit_formatted: '1.0 KB',
      percentage: 9.7,
      mode: 'LIMITED',
      is_full: false,
    },
    negotiations: { current: 5, limit: null, percentage: null, mode: 'UNLIMITED' },
    ai: { enabled: true },
    ai_messages: {
      current: 640,
      limit: 800,
      percentage: 80,
      overage_count: 0,
      mode: 'stop',
      overage_price: 0.05,
      cycle_start: '2026-06-15',
      cycle_end: '2026-07-14',
      cycle_label: '15/jun – 14/jul',
      ...aiOverrides,
    },
  };
}

describe('UsageStatsComponent', () => {
  let fixture: ComponentFixture<UsageStatsComponent>;
  let component: UsageStatsComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [UsageStatsComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(UsageStatsComponent);
    component = fixture.componentInstance;
  });

  it('deve renderizar barra verde quando percentage < 80 (estado normal)', () => {
    fixture.componentRef.setInput('usage', buildUsage({ percentage: 50 }));
    fixture.detectChanges();

    expect(component.aiMsgBarColor()).toBe('bg-primary-500');
    expect(component.aiMsgBarWidth()).toBe(50);
  });

  it('deve renderizar barra amarela quando 80 ≤ percentage < 100 (atenção)', () => {
    fixture.componentRef.setInput('usage', buildUsage({ percentage: 85 }));
    fixture.detectChanges();

    expect(component.aiMsgBarColor()).toBe('bg-warning');
    expect(component.aiMsgBarWidth()).toBe(85);
  });

  it('deve renderizar barra vermelha e texto "IA pausada" quando ≥100% modo stop', () => {
    fixture.componentRef.setInput('usage', buildUsage({ percentage: 100, mode: 'stop', current: 800 }));
    fixture.detectChanges();

    expect(component.aiMsgBarColor()).toBe('bg-error');
    expect(component.aiMsgBarWidth()).toBe(100);

    const errorText = fixture.debugElement.query(By.css('p.text-error'));
    expect(errorText?.nativeElement.textContent).toContain('IA pausada');
  });

  it('deve renderizar texto de cobrança extra quando ≥100% modo overage', () => {
    fixture.componentRef.setInput(
      'usage',
      buildUsage({
        percentage: 110,
        mode: 'overage',
        current: 880,
        overage_count: 80,
        overage_price: 0.05,
      }),
    );
    fixture.detectChanges();

    expect(component.aiMsgBarColor()).toBe('bg-error');
    expect(component.aiMsgBarWidth()).toBe(100);

    const overageText = fixture.debugElement.query(By.css('p.text-neutral-400'));
    expect(overageText?.nativeElement.textContent).toContain('Cobrando extras');
  });

  it('deve exibir cycle_label e contador corretos', () => {
    fixture.componentRef.setInput('usage', buildUsage({ current: 640, limit: 800, cycle_label: '15/jun – 14/jul' }));
    fixture.detectChanges();

    const nativeEl = fixture.nativeElement as HTMLElement;
    expect(nativeEl.textContent).toContain('15/jun – 14/jul');
    expect(nativeEl.textContent).toContain('640/800');
  });
});
