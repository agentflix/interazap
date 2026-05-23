import { type CanActivateFn, type CanDeactivateFn, Router } from '@angular/router';
import { inject } from '@angular/core';
import { AuthStoreService } from '@core/services/auth-store.service';
import type { ForceChangePassword } from '@pages/auth/force-change-password/force-change-password';

/**
 * Guard that restricts access to /auth/force-change-password.
 *
 * Only users with `force_password_change === true` may enter.
 * Everyone else is redirected to `/dashboard`.
 */
export const forceChangePasswordGuard: CanActivateFn = () => {
  const authStore = inject(AuthStoreService);
  const router = inject(Router);

  const user = authStore.user();

  if (user?.force_password_change) {
    return true;
  }

  return router.createUrlTree(['/dashboard']);
};

/**
 * Guard that blocks navigation away from the force-change-password page
 * until the form has been successfully submitted.
 *
 * Shows a browser confirm dialog if the user tries to leave without submitting.
 */
export const forceChangePasswordCanDeactivate: CanDeactivateFn<ForceChangePassword> = (
  component,
  _currentRoute,
  _currentState,
  _nextState,
) => {
  if (component.submitted()) {
    return true;
  }

  return window.confirm(
    'Você precisa redefinir sua senha antes de continuar. Deseja realmente sair?',
  );
};
