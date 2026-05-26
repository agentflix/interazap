import { type CanActivateFn, Router } from '@angular/router';
import { inject } from '@angular/core';
import { AuthStoreService } from '../services/auth-store.service';

/**
 * Protege rotas verificando se o usuário autenticado possui uma permissão específica.
 *
 * O nome da permissão é lido de `route.data['permission']`. Se nenhuma permissão for
 * especificada, o acesso é liberado. Caso o usuário não possua a permissão exigida,
 * redireciona para `/`.
 *
 * @param route - Rota sendo ativada; deve conter `data['permission']` com a permissão requerida
 * @returns `true` se a permissão for satisfeita ou não especificada, ou `UrlTree` redirecionando para `/`
 *
 * @example
 * ```typescript
 * { path: 'users', canActivate: [permissionGuard], data: { permission: 'users.view' } }
 * ```
 */
export const permissionGuard: CanActivateFn = (route) => {
  const authStore = inject(AuthStoreService);
  const router = inject(Router);
  const permission = route.data?.['permission'] as string | undefined;

  if (!permission) {
    return true;
  }

  if (authStore.hasPermission(permission)) {
    return true;
  }

  return router.createUrlTree(['/']);
};
