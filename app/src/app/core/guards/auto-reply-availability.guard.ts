import { inject } from '@angular/core';
import { type CanActivateFn, Router } from '@angular/router';
import { AuthStoreService } from '../services/auth-store.service';

/**
 * Protege rotas de resposta automática legada verificando se as funcionalidades de IA do plano estão inativas.
 *
 * Permite acesso quando a IA está desabilitada ou quando nenhuma das flags de IA
 * (agents_v2, prompts_governance, knowledge_base, usage_tracking) está ativa no plano.
 * Redireciona para `/` quando o tenant já possui IA habilitada com funcionalidades ativas.
 *
 * @returns `true` se resposta automática legada estiver disponível, ou `UrlTree` redirecionando para `/`
 *
 * @example
 * ```typescript
 * { path: 'chat/auto-reply', canActivate: [autoReplyAvailabilityGuard] }
 * ```
 */
export const autoReplyAvailabilityGuard: CanActivateFn = () => {
  const authStore = inject(AuthStoreService);
  const router = inject(Router);

  const plan = authStore.user()?.tenant_plan;
  const aiEnabledWithFeatures =
    Boolean(plan?.ai_enabled) &&
    Boolean(
      plan?.features?.ai_agents_v2 ||
        plan?.features?.ai_prompts_governance ||
        plan?.features?.ai_knowledge_base ||
        plan?.features?.ai_usage_tracking,
    );

  if (aiEnabledWithFeatures) {
    return router.createUrlTree(['/']);
  }

  return true;
};
