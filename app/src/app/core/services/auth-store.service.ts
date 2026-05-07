import { Injectable, computed, signal, inject, DestroyRef } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { PreferencesService } from './preferences.service';
import { ThemeService } from './theme.service';

export interface AuthUser {
  id: string | number;
  name: string;
  email: string;
  avatar_url?: string | null;
  two_factor_enabled?: boolean;
  tenant_id?: string | number | null;
  company_id?: string | number | null;
  is_supervisor?: boolean;
  tenant_plan?: {
    id: string;
    name: string;
    slug: string;
    ai_enabled: boolean;
    features?: {
      ai_agents_v2?: boolean;
      ai_prompts_governance?: boolean;
      ai_knowledge_base?: boolean;
      ai_usage_tracking?: boolean;
      chat_rewrite_v1?: boolean;
    };
  } | null;
  permissions: string[];
  is_impersonating?: boolean;
  impersonated_tenant?: {
    id: string;
    name: string;
  };
}

interface StoredAuth {
  user: AuthUser | null;
  token: string | null;
}

const STORAGE_KEY = 'auth-storage';
const TOKEN_KEY = 'token';
const IMPERSONATION_ORIGINAL_TOKEN_KEY = 'impersonation_original_token';

/**
 * Service for managing authentication state as signals.
 *
 * @remarks
 * Persists auth token and user data in localStorage. Provides computed
 * signals for user, token, and authentication status. Supports permission
 * checking and partial user updates.
 *
 * @example
 * ```typescript
 * const auth = inject(AuthStoreService);
 * if (auth.isAuthenticated()) { ... }
 * auth.hasPermission('crm.contacts.edit');
 * ```
 */
@Injectable({ providedIn: 'root' })
export class AuthStoreService {
  private userSignal = signal<AuthUser | null>(null);
  private tokenSignal = signal<string | null>(null);
  private hydratedSignal = signal(false);

  /** Current authenticated user (null if not authenticated) */
  user = computed(() => this.userSignal());
  /** Current auth token (null if not authenticated) */
  token = computed(() => this.tokenSignal());
  /** Whether the user is currently authenticated */
  isAuthenticated = computed(() => Boolean(this.tokenSignal()));
  /** Whether the service has restored state from localStorage */
  hasHydrated = computed(() => this.hydratedSignal());
  /** Whether the current session is an impersonation */
  isImpersonating = computed(() => Boolean(this.userSignal()?.is_impersonating));
  /** Name of the impersonated tenant */
  impersonatedTenantName = computed(() => {
    const user = this.userSignal();
    if (!user || !user.impersonated_tenant) return null;
    return user.impersonated_tenant.name;
  });

  private readonly preferencesService = inject(PreferencesService, { optional: true });
  private readonly themeService = inject(ThemeService, { optional: true });
  private readonly destroyRef = inject(DestroyRef);

  constructor() {
    this.init();
  }

  /**
   * Initializes the store by restoring state from localStorage.
   */
  init(): void {
    const stored = this.readStorage();
    const token = stored.token ?? this.readToken();
    this.userSignal.set(stored.user);
    this.tokenSignal.set(token && token.trim() !== '' ? token : null);
    this.hydratedSignal.set(true);
  }

  /**
   * Sets authentication data and persists to localStorage.
   *
   * @param user - Authenticated user object
   * @param token - JWT token string
   */
  setAuth(user: AuthUser, token: string): void {
    this.userSignal.set(user);
    this.tokenSignal.set(token);
    this.persist({ user, token });
    this.loadUserPreferences();
  }

  /**
   * Start impersonation by saving the original token and setting the impersonated session.
   */
  startImpersonation(user: AuthUser, token: string): void {
    const currentToken = this.tokenSignal();
    if (currentToken) {
      this.saveOriginalToken(currentToken);
    }
    this.userSignal.set(user);
    this.tokenSignal.set(token);
    this.persist({ user, token });
  }

  /**
   * Stop impersonation and restore the original super admin session.
   */
  stopImpersonation(user: AuthUser, token: string): void {
    this.clearOriginalToken();
    this.userSignal.set(user);
    this.tokenSignal.set(token);
    this.persist({ user, token });
  }

  /**
   * Check if an original token exists (indicates we were impersonating before a page reload).
   */
  hasOriginalToken(): boolean {
    return this.readOriginalToken() !== null;
  }

  /**
   * Load user preferences after login and apply the saved theme.
   * Fails silently — does not block authentication.
   */
  private loadUserPreferences(): void {
    if (!this.preferencesService || !this.themeService) return;
    const themeSvc = this.themeService;
    this.preferencesService.getPreferences().pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (response) => {
        const theme = response.data.appearance?.theme;
        if (theme) {
          themeSvc.applyTheme(theme);
        }
      },
      error: () => {
        // Fallback gracefully — preferences failure does not affect auth state.
      },
    });
  }

  /**
   * Clears authentication data from memory and localStorage.
   */
  logout(): void {
    this.userSignal.set(null);
    this.tokenSignal.set(null);
    this.clearStorage();
  }

  /**
   * Merges partial user data into the current user and persists.
   *
   * @param userUpdate - Partial user object with fields to update
   */
  updateUser(userUpdate: Partial<AuthUser>): void {
    const current = this.userSignal();
    if (!current) {
      return;
    }
    const nextUser = { ...current, ...userUpdate };
    this.userSignal.set(nextUser);
    this.persist({ user: nextUser, token: this.tokenSignal() });
  }

  /**
   * Checks if the current user has a specific permission.
   *
   * @param permission - Permission string to check
   * @returns True if the permission exists in the user's permission list
   */
  hasPermission(permission: string): boolean {
    const current = this.userSignal();
    return current?.permissions.includes(permission) ?? false;
  }

  private readToken(): string | null {
    if (typeof window === 'undefined') {
      return null;
    }
    return localStorage.getItem(TOKEN_KEY);
  }

  private readStorage(): StoredAuth {
    if (typeof window === 'undefined') {
      return { user: null, token: null };
    }
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) {
        return { user: null, token: null };
      }
      const stored = JSON.parse(raw) as StoredAuth;
      if (stored.user && !stored.user.permissions) {
        stored.user.permissions = [];
      }
      return stored;
    } catch {
      return { user: null, token: null };
    }
  }

  private persist(payload: StoredAuth): void {
    if (typeof window === 'undefined') {
      return;
    }
    if (payload.token) {
      localStorage.setItem(TOKEN_KEY, payload.token);
    } else {
      localStorage.removeItem(TOKEN_KEY);
    }
    localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
  }

  private clearStorage(): void {
    if (typeof window === 'undefined') {
      return;
    }
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(STORAGE_KEY);
    localStorage.removeItem(IMPERSONATION_ORIGINAL_TOKEN_KEY);
  }

  private saveOriginalToken(token: string): void {
    if (typeof window === 'undefined') {
      return;
    }
    localStorage.setItem(IMPERSONATION_ORIGINAL_TOKEN_KEY, token);
  }

  private readOriginalToken(): string | null {
    if (typeof window === 'undefined') {
      return null;
    }
    return localStorage.getItem(IMPERSONATION_ORIGINAL_TOKEN_KEY);
  }

  private clearOriginalToken(): void {
    if (typeof window === 'undefined') {
      return;
    }
    localStorage.removeItem(IMPERSONATION_ORIGINAL_TOKEN_KEY);
  }
}
