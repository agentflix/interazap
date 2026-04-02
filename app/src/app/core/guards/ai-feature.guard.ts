import { inject } from '@angular/core';
import { type CanActivateFn, Router } from '@angular/router';
import { AuthStoreService } from '../services/auth-store.service';

/**
 * Defines available AI feature flags that can be checked on a tenant plan.
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
 * Guard that checks if AI features are enabled for the current tenant plan.
 * First verifies that ai_enabled is true on the tenant plan.
 * Then optionally checks for a specific AI feature flag specified in route data.
 * If AI is disabled or the specific feature is not available, redirects to the home page.
 *
 * @param route - The route being activated, with optional data['aiFeature'] specifying which AI feature to check
 * @returns True if AI is enabled and the feature is available (or no feature specified), otherwise a UrlTree redirecting to /
 *
 * @example
 * ```typescript
 * // Check if AI is enabled at all
 * { path: 'ai', canActivate: [aiFeatureGuard] }
 *
 * // Check for specific AI feature
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
