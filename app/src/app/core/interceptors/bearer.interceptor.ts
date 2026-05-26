import { type HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { AuthStorageService } from '../services/platform/auth-storage.service';

/**
 * Interceptor responsável por injetar o header `Authorization: Bearer <token>`.
 *
 * - **Mobile:** sempre usa Bearer (PAT do Capacitor Preferences).
 * - **Web (cookie flow):** sem token em memória → passa inalterado; cookies HttpOnly
 *   são enviados automaticamente via `withCredentials: true`.
 * - **Web (OAuth flow):** quando `AuthStorageService` tem token em memória (após
 *   Google OAuth), injeta o header Bearer para autenticar via Sanctum token guard.
 *
 * Token é lido de forma síncrona do cache em memória do {@link AuthStorageService},
 * que deve ter sido hidratado no boot do app via `authStorage.hydrate()`.
 */
export const bearerInterceptor: HttpInterceptorFn = (req, next) => {
  const authStorage = inject(AuthStorageService);
  const token = authStorage.getSync();

  if (token === null || token === '') {
    return next(req);
  }

  if (req.headers.has('Authorization')) {
    return next(req);
  }

  const authReq = req.clone({
    setHeaders: { Authorization: `Bearer ${token}` },
  });

  return next(authReq);
};
