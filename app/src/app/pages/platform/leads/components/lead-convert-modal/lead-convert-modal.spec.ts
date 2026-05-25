import type { ComponentFixture} from '@angular/core/testing';
import { TestBed } from '@angular/core/testing';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { of } from 'rxjs';
import { LeadConvertModalComponent } from './lead-convert-modal';
import { PlatformLeadService } from '@core/services/platform-lead.service';
import { PlatformPlanService } from '@platform/services/platform-plan.service';

describe('LeadConvertModalComponent', () => {
  let component: LeadConvertModalComponent;
  let fixture: ComponentFixture<LeadConvertModalComponent>;

  const platformLeadServiceMock = {
    convert: vi.fn(),
  };

  const platformPlanServiceMock = {
    list: vi.fn().mockReturnValue(
      of({
        data: [
          {
            id: 'plan-1',
            name: 'Starter',
            slug: 'starter',
            limit_users: 5,
            storage_mode: 'LIMITED',
            ai_enabled: false,
            message_limit_monthly: 800,
            overage_mode: 'stop',
            overage_price_per_message: null,
            whatsapp_integrations_limit: 1,
            negotiations_mode: 'LIMITED',
            price_monthly: '99.00',
            is_active: true,
          },
        ],
        meta: { current_page: 1, total: 1, per_page: 100, last_page: 1 },
      }),
    ),
  };

  beforeEach(async () => {
    platformLeadServiceMock.convert.mockReturnValue(
      of({
        success: true,
        message: 'ok',
        data: {
          id: 'lead-1',
          name: 'Lead',
          email: 'lead@example.com',
          phone: '11999999999',
          lgpd_consent: true,
        },
      }),
    );

    await TestBed.configureTestingModule({
      imports: [LeadConvertModalComponent],
      providers: [
        { provide: PlatformLeadService, useValue: platformLeadServiceMock },
        { provide: PlatformPlanService, useValue: platformPlanServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(LeadConvertModalComponent);
    component = fixture.componentInstance;
  });

  it('pré-preenche formulário com dados do lead', () => {
    fixture.componentRef.setInput('lead', {
      id: 'lead-1',
      name: 'Lead Nome',
      email: 'lead@example.com',
      phone: '11999999999',
      company: 'Empresa Lead',
      lgpd_consent: true,
    });
    fixture.componentRef.setInput('open', true);
    fixture.detectChanges();

    expect(component.form.controls.name.value).toBe('Empresa Lead');
    expect(component.form.controls.email.value).toBe('lead@example.com');
    expect(component.form.controls.phone.value).toBe('11999999999');
  });

  it('envia conversão e emite evento de sucesso', () => {
    const convertedSpy = vi.fn();
    component.converted.subscribe(convertedSpy);

    fixture.componentRef.setInput('lead', {
      id: 'lead-1',
      name: 'Lead Nome',
      email: 'lead@example.com',
      phone: '11999999999',
      lgpd_consent: true,
    });
    fixture.componentRef.setInput('open', true);
    fixture.detectChanges();

    component.form.patchValue({
      name: 'Tenant Convertido',
      email: 'owner@example.com',
      phone: '11911112222',
      document: '12345678900',
      plan_id: 'plan-1',
    });

    component.submit();

    expect(platformLeadServiceMock.convert).toHaveBeenCalledWith('lead-1', {
      name: 'Tenant Convertido',
      email: 'owner@example.com',
      phone: '11911112222',
      document: '12345678900',
      plan_id: 'plan-1',
    });
    expect(convertedSpy).toHaveBeenCalled();
  });
});
