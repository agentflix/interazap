import { type CanActivateFn, Router } from '@angular/router';
import { inject } from '@angular/core';
import { AuthStoreService } from '../services/auth-store.service';

/**
 * Guard that checks if the authenticated user has a specific permission.
 * The permission name is read from the route's data['permission'] property.
 * If no permission is specified, access is allowed.
 * If the user lacks the required permission, redirects to the home page.
 *
 * @param route - The route being activated, must contain data['permission'] with the required permission string
 * @returns True if permission is satisfied or not specified, otherwise a UrlTree redirecting to /
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
