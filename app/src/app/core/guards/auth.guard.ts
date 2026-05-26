import { type CanActivateFn, type CanActivateChildFn, Router } from '@angular/router';
import { inject } from '@angular/core';
import { AuthStoreService } from '../services/auth-store.service';

/**
 * Protege rotas exigindo que o usuário esteja autenticado.
 *
 * Verifica a presença de token válido e dados de usuário no `AuthStoreService`.
 * Se o usuário precisar trocar a senha (`force_password_change`), redireciona para
 * `/auth/force-change-password`. Caso não esteja autenticado, redireciona para `/login`.
 *
 * @returns `true` se autenticado, ou `UrlTree` redirecionando conforme o estado da sessão
 *
 * @example
 * ```typescript
 * { path: 'dashboard', canActivate: [authGuard] }
 * ```
 */
export const authGuard: CanActivateFn = () => {
  const authStore = inject(AuthStoreService);
  const router = inject(Router);

  const token = authStore.token();
  const user = authStore.user();

  if (token && user) {
    // Redirect to force password change if flag is set
    if (user.force_password_change && !router.url.startsWith('/auth/force-change-password')) {
      return router.createUrlTree(['/auth/force-change-password']);
    }
    return true;
  }

  return router.createUrlTree(['/login']);
};

/**
 * Aplica verificação de autenticação em rotas filhas.
 *
 * Delega a lógica de autenticação ao `authGuard`.
 *
 * @param route - Rota filha sendo ativada
 * @param state - Estado do roteador
 * @returns `true` se autenticado, ou `UrlTree` redirecionando para `/login`
 *
 * @example
 * ```typescript
 * { path: 'admin', canActivateChild: [authChildGuard], children: [...] }
 * ```
 */
export const authChildGuard: CanActivateChildFn = (route, state) => {
  return authGuard(route, state);
};
