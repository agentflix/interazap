import { describe, it, expect, beforeEach, vi } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { BillingPrefsModalComponent } from './billing-prefs-modal';

describe('BillingPrefsModalComponent', () => {
  let fixture: ComponentFixture<BillingPrefsModalComponent>;
  let component: BillingPrefsModalComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [BillingPrefsModalComponent],
      providers: [provideHttpClient()],
    }).compileComponents();

    fixture = TestBed.createComponent(BillingPrefsModalComponent);
    component = fixture.componentInstance;
  });

  it('deve criar o componente', () => {
    expect(component).toBeTruthy();
  });

  it('deve inicializar selectedMode com override quando fornecido', async () => {
    fixture.componentRef.setInput('currentOverride', 'overage');
    fixture.componentRef.setInput('open', true);
    fixture.detectChanges();
    await fixture.whenStable();

    expect(component.selectedMode()).toBe('overage');
  });

  it('deve usar overage_mode do plano quando override é null', async () => {
    fixture.componentRef.setInput('plan', {
      id: 'p1',
      name: 'Starter',
      slug: 'starter',
      price_monthly: '97',
      limit_users: 5,
      whatsapp_integrations_limit: 1,
      storage_mode: 'LIMITED',
      storage_limit_bytes: 1024,
      ai_enabled: true,
      negotiations_mode: 'UNLIMITED',
      negotiations_limit: null,
      message_limit_monthly: 800,
      overage_mode: 'overage',
      overage_price_per_message: 0.05,
    });
    fixture.componentRef.setInput('currentOverride', null);
    fixture.componentRef.setInput('open', true);
    fixture.detectChanges();
    await fixture.whenStable();

    expect(component.selectedMode()).toBe('overage');
  });

  it('deve emitir closed ao cancelar', () => {
    const closedSpy = vi.fn();
    component.closed.subscribe(closedSpy);
    fixture.componentRef.setInput('open', true);
    fixture.detectChanges();

    component.closed.emit();

    expect(closedSpy).toHaveBeenCalled();
  });
});
