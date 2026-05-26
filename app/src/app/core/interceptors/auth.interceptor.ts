import { type HttpErrorResponse, type HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { EMPTY, catchError, throwError } from 'rxjs';
import { AuthStoreService } from '../services/auth-store.service';

/**
 * Interceptor de autenticação central.
 *
 * Contexto: HttpInterceptorFn que injeta header `Authorization: Bearer <token>`
 * e trata erros HTTP: 401 (logout + redirect /login), 402 (redirect /financial/invoices),
 * 403 (redirect /access-denied).
 */
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
        return EMPTY;
      }

      return throwError(() => error);
    }),
  );
};
