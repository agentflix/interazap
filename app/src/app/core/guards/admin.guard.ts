import { type CanActivateFn, Router } from '@angular/router';
import { inject } from '@angular/core';
import { AuthStoreService } from '../services/auth-store.service';

/**
 * Protege rotas de administração da plataforma.
 *
 * Permite acesso quando o usuário possui a permissão configurada em
 * `route.data.adminPermission` (default: `users.role.manage`),
 * ou quando o usuário é supervisor ou é um usuário de plataforma (sem tenant_id).
 * Caso contrário, redireciona para `/`.
 */
export const adminGuard: CanActivateFn = (route) => {
  const authStore = inject(AuthStoreService);
  const router = inject(Router);
  const adminPermission =
    (route.data?.['adminPermission'] as string | undefined) ?? 'users.role.manage';

  const user = authStore.user();

  const hasAdminPermission = authStore.hasPermission(adminPermission);
  const isSupervisor = Boolean(user?.is_supervisor);
  const isPlatformUser = (user?.tenant_id === null || user?.tenant_id === undefined) && Boolean(user);

  if (hasAdminPermission || isPlatformUser || isSupervisor) {
    return true;
  }

  return router.createUrlTree(['/']);
};
