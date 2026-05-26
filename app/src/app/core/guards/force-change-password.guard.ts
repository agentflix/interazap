import { type CanActivateFn, type CanDeactivateFn, Router } from '@angular/router';
import { inject } from '@angular/core';
import { AuthStoreService } from '@core/services/auth-store.service';
import type { ForceChangePassword } from '@pages/auth/force-change-password/force-change-password';

/**
 * Protege a rota `/auth/force-change-password`, permitindo acesso apenas a usuários
 * com o flag `force_password_change === true`. Demais usuários são redirecionados para `/dashboard`.
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
 * Bloqueia a saída da página de troca forçada de senha enquanto o formulário
 * não tiver sido enviado com sucesso. Exibe um diálogo de confirmação nativo
 * do navegador caso o usuário tente sair sem concluir o processo.
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
