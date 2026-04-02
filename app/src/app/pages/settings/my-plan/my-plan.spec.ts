import { describe, it, expect, beforeEach, vi } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import MyPlanPage from './my-plan';
import { BillingSubscriptionService } from '@core/services/billing-subscription.service';
import { ToastService } from '@core/services/toast.service';
import {
  type PlanChangePreview,
  type SubscriptionPlan,
  type SubscriptionSummary,
} from '../../../shared/models/subscription.model';

function buildSubscriptionResponse(): { data: SubscriptionSummary } {
  return {
    data: {
      current_plan: {
        id: 'plan-current',
        name: 'Basic',
        slug: 'basic',
        price_monthly: '99.00',
        limit_users: 10,
        whatsapp_integrations_limit: 3,
        storage_mode: 'LIMITED' as const,
        storage_limit_bytes: 1024,
        ai_enabled: true,
        negotiations_mode: 'UNLIMITED' as const,
        negotiations_limit: null,
      },
      usage: {
        users: { current: 2, limit: 10, percentage: 20 },
        instances: { current: 1, limit: 3, percentage: 33.3 },
        storage: {
          used_bytes: 100,
          limit_bytes: 1024,
          used_formatted: '100 B',
          limit_formatted: '1.0 KB',
          percentage: 9.7,
          mode: 'LIMITED' as const,
          is_full: false,
        },
        negotiations: { current: 5, limit: null, percentage: null, mode: 'UNLIMITED' as const },
        ai: { enabled: true },
      },
      next_invoice: {
        due_date: '2026-03-01',
        estimated_amount: '99.00',
        credit_available: '0.00',
      },
    },
  };
}

function buildPlansResponse(): { data: SubscriptionPlan[] } {
  return {
    data: [
      {
        id: 'plan-current',
        name: 'Basic',
        slug: 'basic',
        price_monthly: '99.00',
        limit_users: 10,
        whatsapp_integrations_limit: 3,
        storage_mode: 'LIMITED' as const,
        storage_limit_bytes: 1024,
        ai_enabled: true,
        negotiations_mode: 'UNLIMITED' as const,
        negotiations_limit: null,
        is_current: true,
      },
      {
        id: 'plan-pro',
        name: 'Pro',
        slug: 'pro',
        price_monthly: '199.00',
        limit_users: 25,
        whatsapp_integrations_limit: 10,
        storage_mode: 'LIMITED' as const,
        storage_limit_bytes: 2048,
        ai_enabled: true,
        negotiations_mode: 'UNLIMITED' as const,
        negotiations_limit: null,
        is_current: false,
      },
    ],
  };
}

function buildPreviewResponse(): { data: PlanChangePreview } {
  return {
    data: {
      type: 'upgrade' as const,
      new_plan: { id: 'plan-pro', name: 'Pro', price_monthly: '199.00' },
      financial: {
        pro_rata_charge: '50.00',
        pro_rata_credit: null,
        days_remaining: 14,
        days_in_month: 28,
        formula: '(199.00 - 99.00) × 14 / 28 = 50.00',
      },
      affected_resources: {
        users: { will_deactivate: 0, current: 2, new_limit: 25, affected: [] },
        instances: { will_deactivate: 0, current: 1, new_limit: 10, affected: [] },
        storage: {
          is_over_limit: false,
          current_bytes: 100,
          new_limit_bytes: 2048,
          impact: 'none',
        },
        negotiations: { impact: 'none' as const, message: 'Negociações não serão afetadas' },
      },
    },
  };
}

function buildServiceMock(): {
  getSubscription: ReturnType<typeof vi.fn>;
  getPlans: ReturnType<typeof vi.fn>;
  previewPlanChange: ReturnType<typeof vi.fn>;
  changePlan: ReturnType<typeof vi.fn>;
} {
  return {
    getSubscription: vi.fn().mockReturnValue(of(buildSubscriptionResponse())),
    getPlans: vi.fn().mockReturnValue(of(buildPlansResponse())),
    previewPlanChange: vi.fn().mockReturnValue(of(buildPreviewResponse())),
    changePlan: vi.fn().mockReturnValue(
      of({
        data: {
          type: 'upgrade',
          new_plan: { id: 'plan-pro', name: 'Pro', price_monthly: '199.00' },
          affected_resources: {},
          message: 'Upgrade realizado',
        },
      }),
    ),
  };
}

describe('MyPlanPage', () => {
  let fixture: ComponentFixture<MyPlanPage>;
  let component: MyPlanPage;
  let serviceMock: ReturnType<typeof buildServiceMock>;
  const toastMock = { success: vi.fn(), error: vi.fn() };

  beforeEach(async () => {
    serviceMock = buildServiceMock();

    await TestBed.configureTestingModule({
      imports: [MyPlanPage],
      providers: [
        provideRouter([]),
        { provide: BillingSubscriptionService, useValue: serviceMock },
        { provide: ToastService, useValue: toastMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(MyPlanPage);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('carrega subscription e planos no init', () => {
    expect(serviceMock.getSubscription).toHaveBeenCalled();
    expect(serviceMock.getPlans).toHaveBeenCalled();
    expect(component.loading()).toBe(false);
    expect(component.subscription()?.current_plan?.name).toBe('Basic');
  });

  it('abre modal com preview ao selecionar plano', () => {
    const proPlan = component.plans().find((plan) => plan.id === 'plan-pro');
    if (!proPlan) {
      throw new Error('Plano pro não encontrado no mock');
    }

    const view = component as unknown as { openPlanChange: (plan: { id: string }) => void };
    view.openPlanChange(proPlan);

    expect(serviceMock.previewPlanChange).toHaveBeenCalledWith('plan-pro');
  });

  it('entra em estado de erro quando carregamento falha', () => {
    serviceMock.getSubscription.mockReturnValueOnce(throwError(() => new Error('boom')));
    serviceMock.getPlans.mockReturnValueOnce(throwError(() => new Error('boom')));

    const view = component as unknown as { load: () => void };
    view.load();
    fixture.detectChanges();

    expect(component.error()).toBeTruthy();
    expect(component.loading()).toBe(false);
  });
});
