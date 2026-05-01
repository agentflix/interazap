import { type HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { AuthStorageService } from '../services/platform/auth-storage.service';
import { PlatformService } from '../services/platform/platform.service';

/**
 * Interceptor responsável por injetar o header `Authorization: Bearer <token>`
 * **apenas em mobile** (iOS/Android via Capacitor).
 *
 * No web o request passa inalterado: a autenticação é feita por cookies
 * HttpOnly emitidos pelo backend (Sanctum SPA), e basta `withCredentials: true`
 * no request para que o navegador os envie automaticamente.
 *
 * Token é lido de forma síncrona do cache em memória do {@link AuthStorageService},
 * que deve ter sido hidratado no boot do app via `authStorage.hydrate()`.
 */
export const bearerInterceptor: HttpInterceptorFn = (req, next) => {
  const platform = inject(PlatformService);

  if (!platform.isMobile) {
    return next(req);
  }

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
