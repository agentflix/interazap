import { Injectable, inject } from '@angular/core';
import { AuthStoreService } from './auth-store.service';

const FORCE_KEY = 'chat_rewrite_v1_force';
const ROLLOUT_KEY = 'chat_rewrite_v1_rollout';

/**
 * Controls canary rollout for chat rewrite.
 *
 * Priority order:
 * 1) local override (`chat_rewrite_v1_force` = on/off)
 * 2) tenant plan feature (`chat_rewrite_v1`) + rollout bucket
 */
@Injectable({ providedIn: 'root' })
export class ChatRewriteRolloutService {
  private readonly authStore = inject(AuthStoreService);

  isEnabledForCurrentUser(): boolean {
    const force = this.getForcedDecision();
    if (force !== null) {
      return force;
    }

    const user = this.authStore.user();
    const featureEnabled = Boolean(user?.tenant_plan?.features?.chat_rewrite_v1);
    if (!featureEnabled) {
      return false;
    }

    const rollout = this.getRolloutPercentage();
    if (rollout >= 100) {
      return true;
    }

    const stableKey = `${user?.tenant_id ?? user?.company_id ?? 'tenant'}:${user?.id ?? 'user'}`;
    const bucket = this.computeStableBucket(stableKey);
    return bucket < rollout;
  }

  private getForcedDecision(): boolean | null {
    if (typeof window === 'undefined') {
      return null;
    }

    const forced = window.localStorage.getItem(FORCE_KEY);
    if (forced === 'on') {
      return true;
    }

    if (forced === 'off') {
      return false;
    }

    return null;
  }

  private getRolloutPercentage(): number {
    if (typeof window === 'undefined') {
      return 100;
    }

    const raw = window.localStorage.getItem(ROLLOUT_KEY);
    if (raw === null) {
      return 100;
    }

    const parsed = Number(raw);
    if (!Number.isFinite(parsed)) {
      return 100;
    }

    return Math.max(0, Math.min(100, Math.floor(parsed)));
  }

  private computeStableBucket(key: string): number {
    let hash = 0;
    for (let i = 0; i < key.length; i += 1) {
      hash = (hash * 31 + key.charCodeAt(i)) | 0;
    }

    return Math.abs(hash) % 100;
  }
}
