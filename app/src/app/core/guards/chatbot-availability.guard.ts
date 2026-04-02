import { inject } from '@angular/core';
import { type CanActivateFn, Router } from '@angular/router';
import { AuthStoreService } from '../services/auth-store.service';

/**
 * Guard that restricts access to chatbot-related routes based on tenant plan AI features.
 * Allows access when AI is disabled OR when none of the AI features (agents, prompts governance,
 * knowledge base, usage tracking) are enabled on the plan.
 * This guard protects routes that should only be accessible when the legacy chatbot is available.
 *
 * @returns True if chatbot is available (AI features disabled or unavailable), otherwise a UrlTree redirecting to /
 *
 * @example
 * ```typescript
 * { path: 'chat/chatbot', canActivate: [chatbotAvailabilityGuard] }
 * ```
 */
export const chatbotAvailabilityGuard: CanActivateFn = () => {
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
