import { type HttpErrorResponse, type HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { EMPTY, catchError, throwError } from 'rxjs';
import { BillingStatusService, type BillingLockoutData } from '../services/billing-status.service';

/**
 * Captures billing lockout responses and redirects user to lockout page.
 */
export const billingLockoutInterceptor: HttpInterceptorFn = (req, next) => {
  const router = inject(Router);
  const billingStatusService = inject(BillingStatusService);

  const isAuthRoute = req.url.includes('/auth/');

  return next(req).pipe(
    catchError((error: HttpErrorResponse) => {
      if (!isAuthRoute && error.status === 423 && error.error?.error === 'tenant_locked') {
        billingStatusService.setLocked(error.error as BillingLockoutData);

        if (!router.url.startsWith('/billing/lockout')) {
          void router.navigate(['/billing/lockout']);
        }

        return EMPTY;
      }

      return throwError(() => error);
    }),
  );
};
