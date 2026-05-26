import { Injectable, inject } from '@angular/core';
import { Preferences } from '@capacitor/preferences';
import { PlatformService } from './platform.service';

const TOKEN_KEY = 'auth_token';

/**
 * Persiste o token de autenticação de forma adequada à plataforma.
 *
 * - **Web (cookie flow):** no-op na maioria dos logins — cookies HttpOnly emitidos
 *   pelo backend são autoritativos e enviados automaticamente via `withCredentials`.
 * - **Web (OAuth flow):** quando o backend retorna um Bearer token (ex: Google OAuth),
 *   persiste em `localStorage` + cache em memória para uso pelo `bearerInterceptor`.
 * - **Mobile (iOS/Android via Capacitor):** usa `@capacitor/preferences`,
 *   que internamente mapeia para Keychain (iOS) e EncryptedSharedPreferences
 *   (Android), garantindo armazenamento seguro do PAT (Personal Access Token).
 *
 * O cache em memória (`memoryToken`) permite leitura síncrona pelo
 * `bearerInterceptor`, evitando converter cada request HTTP em Promise.
 * O cache é hidratado uma única vez no boot via {@link hydrate}.
 *
 * @example
 * ```ts
 * await authStorage.hydrate();           // boot do app
 * await authStorage.set(token);          // após login bem-sucedido (OAuth ou mobile)
 * const token = authStorage.getSync();   // dentro do interceptor
 * await authStorage.clear();             // logout
 * ```
 */
@Injectable({ providedIn: 'root' })
export class AuthStorageService {
  private readonly platform = inject(PlatformService);
  private memoryToken: string | null = null;
  private hydrated = false;

  /**
   * Hidrata o cache em memória a partir do storage nativo.
   * Deve ser chamado no boot do app (antes do primeiro request autenticado).
   * Em web, carrega token de `localStorage` (persistido após OAuth).
   */
  async hydrate(): Promise<void> {
    if (this.hydrated) {
      return;
    }
    if (!this.platform.isMobile) {
      const stored = localStorage.getItem(TOKEN_KEY);
      this.memoryToken = stored && stored.trim() !== '' ? stored : null;
      this.hydrated = true;
      return;
    }
    try {
      const value = await this.readTokenFromStorage();
      this.memoryToken = value && value.trim() !== '' ? value : null;
    } catch {
      this.memoryToken = null;
    }
    this.hydrated = true;
  }

  /**
   * Persiste o token. Em web, salva em `localStorage` para sobreviver a refreshes.
   * Usado no fluxo OAuth onde o backend retorna Bearer token em vez de cookie.
   */
  async set(token: string): Promise<void> {
    this.memoryToken = token;
    if (!this.platform.isMobile) {
      localStorage.setItem(TOKEN_KEY, token);
      return;
    }
    await this.writeTokenToStorage(token);
  }

  /**
   * Remove o token persistido (web + mobile).
   */
  async clear(): Promise<void> {
    this.memoryToken = null;
    if (!this.platform.isMobile) {
      localStorage.removeItem(TOKEN_KEY);
      return;
    }
    await this.removeTokenFromStorage();
  }

  /**
   * Leitura assíncrona do token.
   * Em web retorna `memoryToken` (populado após OAuth ou hydrate).
   */
  async get(): Promise<string | null> {
    if (!this.platform.isMobile) {
      return this.memoryToken;
    }
    if (this.memoryToken !== null) {
      return this.memoryToken;
    }
    try {
      const value = await this.readTokenFromStorage();
      this.memoryToken = value && value.trim() !== '' ? value : null;
      return this.memoryToken;
    } catch {
      return null;
    }
  }

  private async readTokenFromStorage(): Promise<string | null> {
    const { value } = await Preferences.get({ key: TOKEN_KEY });
    return value ?? null;
  }

  private async writeTokenToStorage(token: string): Promise<void> {
    await Preferences.set({ key: TOKEN_KEY, value: token });
  }

  private async removeTokenFromStorage(): Promise<void> {
    await Preferences.remove({ key: TOKEN_KEY });
  }

  /**
   * Leitura síncrona do cache em memória — usada pelo `bearerInterceptor`.
   * Requer que {@link hydrate} tenha sido executado previamente.
   * Retorna token tanto em mobile quanto em web (após OAuth).
   */
  getSync(): string | null {
    return this.memoryToken;
  }
}
