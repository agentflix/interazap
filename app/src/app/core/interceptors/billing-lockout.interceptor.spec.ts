import { HttpErrorResponse, HttpRequest, type HttpHandlerFn } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { firstValueFrom, throwError } from 'rxjs';
import { BillingStatusService } from '../services/billing-status.service';
import { billingLockoutInterceptor } from './billing-lockout.interceptor';

describe('billingLockoutInterceptor', () => {
  let router: Router;
  let statusService: BillingStatusService;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideRouter([]), BillingStatusService],
    });

    router = TestBed.inject(Router);
    statusService = TestBed.inject(BillingStatusService);
  });

  it('stores lockout state and redirects when receives 423 tenant_locked', async () => {
    vi.spyOn(router, 'navigate').mockResolvedValue(true);

    const request = new HttpRequest('GET', '/api/crm/contacts');
    const responseError = new HttpErrorResponse({
      status: 423,
      error: {
        error: 'tenant_locked',
        message: 'Locked',
        billing_status: 'locked',
        locked_at: '2026-02-01T00:00:00Z',
        overdue_invoices: [],
        purge_deadline: null,
      },
    });

    const handler: HttpHandlerFn = () => throwError(() => responseError);

    await expect(
      firstValueFrom(
        TestBed.runInInjectionContext(() => billingLockoutInterceptor(request, handler)),
        { defaultValue: null },
      ),
    ).resolves.toBeNull();

    expect(statusService.isLocked()).toBe(true);
    expect(router.navigate).toHaveBeenCalledWith(['/billing/lockout']);
  });

  it('does not redirect for auth requests', async () => {
    vi.spyOn(router, 'navigate').mockResolvedValue(true);

    const request = new HttpRequest('GET', '/api/auth/login');
    const responseError = new HttpErrorResponse({
      status: 423,
      error: {
        error: 'tenant_locked',
        message: 'Locked',
        billing_status: 'locked',
        locked_at: null,
        overdue_invoices: [],
        purge_deadline: null,
      },
    });

    const handler: HttpHandlerFn = () => throwError(() => responseError);

    await expect(
      firstValueFrom(
        TestBed.runInInjectionContext(() => billingLockoutInterceptor(request, handler)),
      ),
    ).rejects.toBe(responseError);

    expect(statusService.isLocked()).toBe(false);
    expect(router.navigate).not.toHaveBeenCalled();
  });
});
