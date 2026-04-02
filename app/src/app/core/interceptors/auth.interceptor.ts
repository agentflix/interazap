import { type HttpErrorResponse, type HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { EMPTY, catchError, throwError } from 'rxjs';
import { AuthStoreService } from '../services/auth-store.service';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const authStore = inject(AuthStoreService);
  const router = inject(Router);
  const token = authStore.token();

  const authReq = token
    ? req.clone({
        setHeaders: {
          Authorization: `Bearer ${token}`,
        },
      })
    : req;

  return next(authReq).pipe(
    catchError((error: HttpErrorResponse) => {
      if (error.status === 402) {
        const current = router.url;
        if (!current.startsWith('/financial/invoices')) {
          void router.navigate(['/financial/invoices']);
        }
      }

      if (error.status === 403) {
        const current = router.url;
        if (!current.startsWith('/access-denied')) {
          const errorMessage = error.error?.message || 'This action is unauthorized.';
          void router.navigate(['/access-denied'], {
            queryParams: { message: encodeURIComponent(errorMessage) },
          });
        }
        return EMPTY;
      }

      if (error.status === 401) {
        authStore.logout();
        void router.navigate(['/login']);
      }

      return throwError(() => error);
    }),
  );
};
