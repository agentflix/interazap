import { inject } from '@angular/core';
import { type CanActivateFn, Router } from '@angular/router';
import { AuthStoreService } from '../services/auth-store.service';

/**
 * Flags de funcionalidades de IA disponíveis para verificação no plano do tenant.
 *
 * @example
 * ```typescript
 * const features: AiPlanFeatures = {
 *   ai_agents_v2: true,
 *   ai_knowledge_base: false
 * };
 * ```
 */
interface AiPlanFeatures {
  ai_agents_v2?: boolean;
  ai_prompts_governance?: boolean;
  ai_knowledge_base?: boolean;
  ai_usage_tracking?: boolean;
}

/**
 * Protege rotas de IA verificando se as funcionalidades de IA estão habilitadas no plano do tenant.
 *
 * Primeiro verifica se `ai_enabled` é verdadeiro no plano. Em seguida, verifica
 * opcionalmente uma flag de funcionalidade específica informada em `route.data['aiFeature']`.
 * Redireciona para `/` se a IA estiver desabilitada ou a funcionalidade não estiver disponível.
 *
 * @param route - Rota sendo ativada; `data['aiFeature']` especifica qual flag verificar
 * @returns `true` se a IA e a funcionalidade estiverem disponíveis, ou `UrlTree` redirecionando para `/`
 *
 * @example
 * ```typescript
 * // Verifica apenas se IA está habilitada
 * { path: 'ai', canActivate: [aiFeatureGuard] }
 *
 * // Verifica funcionalidade específica de IA
 * { path: 'ai/agents', canActivate: [aiFeatureGuard], data: { aiFeature: 'ai_agents_v2' } }
 * ```
 */
export const aiFeatureGuard: CanActivateFn = (route) => {
  const authStore = inject(AuthStoreService);
  const router = inject(Router);

  const user = authStore.user();
  const plan = user?.tenant_plan;

  if (!plan?.ai_enabled) {
    return router.createUrlTree(['/']);
  }

  const featureKey = route.data?.['aiFeature'] as keyof AiPlanFeatures | undefined;
  if (!featureKey) {
    return true;
  }

  const features = (plan.features ?? {}) as AiPlanFeatures;
  const isEnabled = Boolean(features[featureKey]);

  if (isEnabled) {
    return true;
  }

  return router.createUrlTree(['/']);
};
