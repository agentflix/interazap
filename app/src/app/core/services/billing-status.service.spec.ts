import { TestBed } from '@angular/core/testing';
import { beforeEach, describe, expect, it } from 'vitest';
import { BillingStatusService, type BillingLockoutData } from './billing-status.service';

describe('BillingStatusService', () => {
  let service: BillingStatusService;

  const lockoutData: BillingLockoutData = {
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
        payment_url: 'https://pay.example.com/inv-1',
      },
    ],
    purge_deadline: '2026-02-20T00:00:00Z',
  };

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [BillingStatusService] });
    service = TestBed.inject(BillingStatusService);
  });

  it('starts unlocked by default', () => {
    expect(service.lockoutData()).toBeNull();
    expect(service.isLocked()).toBe(false);
  });

  it('stores lockout payload when setLocked is called', () => {
    service.setLocked(lockoutData);

    expect(service.lockoutData()).toEqual(lockoutData);
    expect(service.isLocked()).toBe(true);
  });

  it('clears lockout payload when clearLockout is called', () => {
    service.setLocked(lockoutData);
    service.clearLockout();

    expect(service.lockoutData()).toBeNull();
    expect(service.isLocked()).toBe(false);
  });
});
