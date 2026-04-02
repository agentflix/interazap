import { type CanActivateFn, type CanActivateChildFn, Router } from '@angular/router';
import { inject } from '@angular/core';
import { AuthStoreService } from '../services/auth-store.service';

/**
 * Guard that ensures the user is authenticated before allowing access to a route.
 * Checks for both a valid token and user data in the AuthStoreService.
 * If not authenticated, redirects to the login page.
 *
 * @param route - The route being activated (unused in this guard)
 * @param state - The router state (unused in this guard)
 * @returns True if authenticated, otherwise a UrlTree redirecting to /login
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
    return true;
  }

  return router.createUrlTree(['/login']);
};

/**
 * Guard that applies authentication check to child routes.
 * Delegates to authGuard for the actual authentication logic.
 *
 * @param route - The child route being activated
 * @param state - The router state
 * @returns True if authenticated, otherwise a UrlTree redirecting to /login
 *
 * @example
 * ```typescript
 * { path: 'admin', canActivateChild: [authChildGuard], children: [...] }
 * ```
 */
export const authChildGuard: CanActivateChildFn = (route, state) => {
  return authGuard(route, state);
};
