import { Injectable, computed, signal, inject, DestroyRef } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { type AuthUser, type StoredAuth } from '@core/models/auth.model';
import { PreferencesService } from './preferences.service';
import { ThemeService } from './theme.service';

const STORAGE_KEY = 'auth-storage';
const TOKEN_KEY = 'token';
const IMPERSONATION_ORIGINAL_TOKEN_KEY = 'impersonation_original_token';

/**
 * Gerencia o estado de autenticação como signals reativos.
 *
 * Persiste token e dados do usuário no localStorage e expõe computed signals
 * para usuário, token e status de autenticação. Suporta verificação de
 * permissões e atualização parcial do usuário. Gerencia o fluxo de
 * impersonação preservando o token original do super admin.
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

  /** Usuário autenticado atual (null se não autenticado). */
  user = computed(() => this.userSignal());
  /** Token de autenticação atual (null se não autenticado). */
  token = computed(() => this.tokenSignal());
  /** Indica se há uma sessão autenticada ativa. */
  isAuthenticated = computed(() => Boolean(this.tokenSignal()));
  /** Indica se o estado foi restaurado do localStorage. */
  hasHydrated = computed(() => this.hydratedSignal());
  /** Indica se a sessão atual é uma impersonação de tenant ou usuário. */
  isImpersonating = computed(() => Boolean(this.userSignal()?.is_impersonating));
  /** Nome do tenant sendo impersonado (null fora de impersonação). */
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

  /** Inicializa o store restaurando estado persistido no localStorage. */
  init(): void {
    const stored = this.readStorage();
    const token = stored.token ?? this.readToken();
    this.userSignal.set(stored.user);
    this.tokenSignal.set(token && token.trim() !== '' ? token : null);
    this.hydratedSignal.set(true);
  }

  /**
   * Define os dados de autenticação e persiste no localStorage.
   *
   * @param user Objeto do usuário autenticado
   * @param token String do token de acesso
   */
  setAuth(user: AuthUser, token: string): void {
    this.userSignal.set(user);
    this.tokenSignal.set(token);
    this.persist({ user, token });
    this.loadUserPreferences();
  }

  /** Inicia impersonação salvando o token original e ativando a sessão impersonada. */
  startImpersonation(user: AuthUser, token: string): void {
    const currentToken = this.tokenSignal();
    if (currentToken) {
      this.saveOriginalToken(currentToken);
    }
    this.userSignal.set(user);
    this.tokenSignal.set(token);
    this.persist({ user, token });
  }

  /** Encerra impersonação e restaura a sessão original do super admin. */
  stopImpersonation(user: AuthUser, token: string): void {
    this.clearOriginalToken();
    this.userSignal.set(user);
    this.tokenSignal.set(token);
    this.persist({ user, token });
  }

  /** Verifica se existe token original salvo (indica impersonação ativa antes de recarga). */
  hasOriginalToken(): boolean {
    return this.readOriginalToken() !== null;
  }

  /**
   * Carrega preferências do usuário após login e aplica o tema salvo.
   * Falha silenciosamente — não bloqueia a autenticação.
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
        // Falha silenciosa — erro de preferências não afeta o estado de autenticação.
      },
    });
  }

  /** Limpa dados de autenticação da memória e do localStorage. */
  logout(): void {
    this.userSignal.set(null);
    this.tokenSignal.set(null);
    this.clearStorage();
  }

  /**
   * Mescla dados parciais no usuário atual e persiste no localStorage.
   *
   * @param userUpdate Objeto parcial com campos a atualizar
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
   * Verifica se o usuário atual possui uma permissão específica.
   *
   * @param permission String da permissão a verificar
   * @returns true se a permissão estiver na lista do usuário
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
