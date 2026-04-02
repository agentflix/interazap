import { TestBed } from '@angular/core/testing';
import { AuthStoreService, type AuthUser } from './auth-store.service';
import { ChatRewriteRolloutService } from './chat-rewrite-rollout.service';

function buildUser(featureEnabled: boolean): AuthUser {
  return {
    id: 'user-1',
    name: 'Tester',
    email: 'tester@example.com',
    company_id: 'tenant-1',
    permissions: [],
    tenant_plan: {
      id: 'plan-1',
      name: 'Plan',
      slug: 'plan',
      ai_enabled: true,
      features: {
        chat_rewrite_v1: featureEnabled,
      },
    },
  };
}

describe('ChatRewriteRolloutService', () => {
  let service: ChatRewriteRolloutService;
  let authStore: AuthStoreService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(ChatRewriteRolloutService);
    authStore = TestBed.inject(AuthStoreService);
    localStorage.removeItem('chat_rewrite_v1_force');
    localStorage.removeItem('chat_rewrite_v1_rollout');
  });

  it('should return false when tenant feature is disabled', () => {
    authStore.setAuth(buildUser(false), 'token');

    expect(service.isEnabledForCurrentUser()).toBe(false);
  });

  it('should return true when tenant feature is enabled and no override exists', () => {
    authStore.setAuth(buildUser(true), 'token');

    expect(service.isEnabledForCurrentUser()).toBe(true);
  });

  it('should return false when forced off', () => {
    authStore.setAuth(buildUser(true), 'token');
    localStorage.setItem('chat_rewrite_v1_force', 'off');

    expect(service.isEnabledForCurrentUser()).toBe(false);
  });

  it('should return true when forced on', () => {
    authStore.setAuth(buildUser(false), 'token');
    localStorage.setItem('chat_rewrite_v1_force', 'on');

    expect(service.isEnabledForCurrentUser()).toBe(true);
  });

  it('should return false when rollout is set to 0', () => {
    authStore.setAuth(buildUser(true), 'token');
    localStorage.setItem('chat_rewrite_v1_rollout', '0');

    expect(service.isEnabledForCurrentUser()).toBe(false);
  });
});
