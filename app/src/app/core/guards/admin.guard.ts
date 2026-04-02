import { type CanActivateFn, Router } from '@angular/router';
import { inject } from '@angular/core';
import { AuthStoreService } from '../services/auth-store.service';

/**
 * Guard for super admin platform pages.
 */
export const adminGuard: CanActivateFn = (route) => {
  const authStore = inject(AuthStoreService);
  const router = inject(Router);
  const adminPermission =
    (route.data?.['adminPermission'] as string | undefined) ?? 'users.role.manage';

  const user = authStore.user();

  const hasAdminPermission = authStore.hasPermission(adminPermission);
  const isSupervisor = Boolean(user?.is_supervisor);
  const isPlatformUser = user?.tenant_id === null || user?.tenant_id === undefined;

  if (hasAdminPermission || isSupervisor || isPlatformUser) {
    return true;
  }

  return router.createUrlTree(['/']);
};
