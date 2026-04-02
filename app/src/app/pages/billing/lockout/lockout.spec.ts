import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AuthStoreService } from '@core/services/auth-store.service';
import { BillingStatusService } from '@core/services/billing-status.service';
import { LockoutPage } from './lockout';

describe('LockoutPage', () => {
  let fixture: ComponentFixture<LockoutPage>;
  let component: LockoutPage;
  let router: Router;
  let billingStatusService: BillingStatusService;

  const authStoreMock = {
    logout: vi.fn(),
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [LockoutPage],
      providers: [
        provideRouter([]),
        BillingStatusService,
        { provide: AuthStoreService, useValue: authStoreMock },
      ],
    }).compileComponents();

    router = TestBed.inject(Router);
    billingStatusService = TestBed.inject(BillingStatusService);

    billingStatusService.setLocked({
      error: 'tenant_locked',
      message: 'Tenant locked due to overdue invoices.',
      billing_status: 'locked',
      locked_at: '2026-02-01T00:00:00Z',
      overdue_invoices: [
        {
          id: 'inv-1',
          reference_month: '2026-01',
          amount: 19900,
          due_date: '2026-01-15',
        },
      ],
      purge_deadline: '2026-02-20T00:00:00Z',
    });

    fixture = TestBed.createComponent(LockoutPage);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('navigates to invoice payment route when payNow is clicked', () => {
    const navigateSpy = vi.spyOn(router, 'navigate').mockResolvedValue(true);

    component.payNow();

    expect(navigateSpy).toHaveBeenCalledWith(['/billing/invoices', 'inv-1', 'pay']);
  });

  it('navigates to my plan page', () => {
    const navigateSpy = vi.spyOn(router, 'navigate').mockResolvedValue(true);

    component.goToMyPlan();

    expect(navigateSpy).toHaveBeenCalledWith(['/settings/my-plan']);
  });

  it('logs out and clears lockout state', () => {
    const navigateSpy = vi.spyOn(router, 'navigate').mockResolvedValue(true);

    component.logout();

    expect(authStoreMock.logout).toHaveBeenCalledTimes(1);
    expect(billingStatusService.isLocked()).toBe(false);
    expect(navigateSpy).toHaveBeenCalledWith(['/login']);
  });
});
