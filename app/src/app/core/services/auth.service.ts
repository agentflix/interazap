import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Device } from '@capacitor/device';
import { catchError, from, of, switchMap, tap, type Observable } from 'rxjs';
import { environment } from '@env/environment';
import { type AuthResponse, type AuthUser, type MenuResponse } from '@core/models/auth.model';
import { AuthStorageService } from './platform/auth-storage.service';
import { PlatformService } from './platform/platform.service';
import { PushService } from './platform/push.service';

/**
 * Servico responsavel por todas as operacoes de autenticacao:
 * login, logout, refresh de token, 2FA e recuperacao de senha.
 *
 * @class AuthService
 * @example
 * ```ts
 * const auth = inject(AuthService);
 * auth.login({ email: 'user@example.com', password: 'secret' }).subscribe();
 * ```
 */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private baseUrl = environment.apiUrl;
  private readonly http = inject(HttpClient);
  private readonly platform = inject(PlatformService);
  private readonly authStorage = inject(AuthStorageService);
  private readonly pushService = inject(PushService);

  /**
   * Autentica o usuario com email e senha.
   *
   * Em mobile, envia `device_name` (vindo de `Device.getInfo()` quando
   * disponível) para que o backend emita um Personal Access Token Sanctum
   * dedicado àquele dispositivo. Em web, `device_name` é omitido e a sessão
   * vive em cookies HttpOnly emitidos pelo backend.
   *
   * @param credentials - Objeto contendo email e password
   * @returns Observable com dados do usuario e token de acesso
   */
  login(credentials: { email: string; password: string }): Observable<AuthResponse> {
    return this.buildLoginPayload(credentials).pipe(
      switchMap((payload) =>
        this.http
          .post<AuthResponse>(`${this.baseUrl}/auth/login`, payload, {
            withCredentials: true,
          })
          .pipe(switchMap((response) => this.persistToken(response))),
      ),
    );
  }

  /**
   * Autentica o usuario com codigo de segundo fator (2FA).
   *
   * @param email - Email do usuario
   * @param code - Codigo de verificacao de 6 digitos
   * @returns Observable com dados do usuario e token de acesso
   */
  loginWith2FA(email: string, code: string): Observable<AuthResponse> {
    return this.buildLoginPayload({ email, code }).pipe(
      switchMap((payload) =>
        this.http
          .post<AuthResponse>(`${this.baseUrl}/auth/login-with-2fa`, payload, {
            withCredentials: true,
          })
          .pipe(switchMap((response) => this.persistToken(response))),
      ),
    );
  }

  /**
   * Encerra a sessao do usuario autenticado:
   * - chama `POST /api/auth/logout` (que revoga o PAT atual em mobile)
   * - limpa o token persistido em mobile via {@link AuthStorageService}
   *
   * @returns Observable vazio indicando sucesso
   */
  logout(): Observable<null> {
    return from(this.pushService.unregisterCurrentDevice()).pipe(
      catchError(() => of(undefined)),
      switchMap(() => this.http.post<null>(`${this.baseUrl}/auth/logout`, {}, { withCredentials: true })),
      switchMap((response) => from(this.authStorage.clear()).pipe(switchMap(() => of(response)))),
    );
  }

  /**
   * Retorna os dados do usuario autenticado atualmente.
   *
   * @returns Observable com dados do usuario e plano vigente
   */
  me(): Observable<AuthResponse> {
    return this.http.get<AuthResponse>(`${this.baseUrl}/auth/me`, { withCredentials: true });
  }

  /**
   * Recupera a estrutura do menu de navegacao para o usuario.
   *
   * @returns Observable com a arvore de menus
   */
  getMenu(): Observable<MenuResponse> {
    return this.http.get<MenuResponse>(`${this.baseUrl}/auth/get-menu`, {
      withCredentials: true,
    });
  }

  /**
   * Renova o token de acesso usando refresh token.
   *
   * @returns Observable com novos dados de autenticacao
   */
  refresh(): Observable<AuthResponse> {
    return this.http
      .post<AuthResponse>(`${this.baseUrl}/auth/refresh`, {}, { withCredentials: true })
      .pipe(switchMap((response) => this.persistToken(response)));
  }

  /**
   * Impersona um tenant como super admin.
   *
   * @param tenantId - ID do tenant a ser impersonado
   * @param password - Senha do super admin para validacao
   * @returns Observable com dados da sessao do usuario admin do tenant
   */
  impersonateTenant(tenantId: string, password: string): Observable<AuthResponse> {
    return this.http
      .post<AuthResponse>(
        `${this.baseUrl}/platform/tenants/${tenantId}/impersonate`,
        { password },
        { withCredentials: true },
      )
      .pipe(switchMap((response) => this.persistToken(response)));
  }

  /**
   * Impersona um usuário específico como super admin.
   *
   * @param userId - ID do usuário a ser impersonado
   * @param password - Senha do super admin para validação
   * @returns Observable com dados da sessão do usuário target
   */
  impersonateUser(userId: string, password: string): Observable<AuthResponse> {
    return this.http
      .post<AuthResponse>(
        `${this.baseUrl}/platform/users/${userId}/impersonate`,
        { password },
        { withCredentials: true },
      )
      .pipe(switchMap((response) => this.persistToken(response)));
  }

  /**
   * Encerra a impersonacao e retorna a sessao do super admin original.
   *
   * @returns Observable com dados da sessao original
   */
  stopImpersonating(): Observable<AuthResponse> {
    return this.http
      .post<AuthResponse>(`${this.baseUrl}/auth/stop-impersonating`, {}, { withCredentials: true })
      .pipe(switchMap((response) => this.persistToken(response)));
  }

  /**
   * Envia email de recuperacao de senha.
   *
   * @param email - Email do usuario que esqueceu a senha
   * @returns Observable com indicador de sucesso e mensagem
   */
  forgotPassword(email: string): Observable<{ success: boolean; message: string }> {
    return this.http.post<{ success: boolean; message: string }>(
      `${this.baseUrl}/auth/forgot-password`,
      { email },
      { withCredentials: true },
    );
  }

  resetPassword(payload: {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
  }): Observable<{ success: boolean; message: string }> {
    return this.http.post<{ success: boolean; message: string }>(
      `${this.baseUrl}/auth/reset-password`,
      payload,
      { withCredentials: true },
    );
  }

  /**
   * Constrói o payload de login adicionando `device_name` apenas em mobile.
   *
   * O backend (TASK-047.3) usa esse identificador como o `name` do PAT
   * Sanctum, permitindo gestão de múltiplos dispositivos por usuário.
   *
   * @internal
   */

  /**
   * Cadastra um novo usuário via formulário público de signup.
   * Cria tenant + user + plano trial em uma única requisição.
   *
   * @param payload - Dados de cadastro (name, email, password, accept_terms)
   * @returns Observable com dados do usuário, token e plano trial ativo
   */
  completeSignup(payload: { company_name: string; phone: string; password: string }): Observable<{ data: AuthUser }> {
    return this.http.post<{ data: AuthUser }>(`${this.baseUrl}/auth/complete-signup`, payload, {
      withCredentials: true,
    });
  }

  signup(payload: { name: string; email: string; password: string; accept_terms: boolean }): Observable<AuthResponse> {
    return this.http
      .post<AuthResponse>(`${this.baseUrl}/auth/signup`, payload, { withCredentials: true })
      .pipe(switchMap((response) => this.persistToken(response)));
  }

  /**
   * Processa o token recebido após callback do Google OAuth.
   * Persiste o token e busca dados do usuário autenticado via GET /auth/me.
   *
   * @param token - Token Sanctum emitido pelo backend no callback OAuth
   * @returns Observable com dados completos do usuário autenticado
   */
  handleGoogleToken(token: string): Observable<AuthResponse> {
    return from(this.authStorage.set(token)).pipe(
      switchMap(() => this.me()),
      switchMap((response) => this.persistToken(response)),
    );
  }

  private buildLoginPayload<T extends Record<string, unknown>>(payload: T): Observable<T & { device_name?: string }> {
    if (!this.platform.isMobile) {
      return of(payload);
    }
    return from(this.resolveDeviceName()).pipe(
      switchMap((deviceName) => of({ ...payload, device_name: deviceName })),
    );
  }

  /**
   * Gera um identificador estável-o-suficiente para o dispositivo.
   *
   * Tenta usar {@link Device.getInfo} (Capacitor) e cai em um fallback
   * baseado em plataforma + timestamp se a API estiver indisponível
   * (ex.: durante testes ou ambientes não-nativos).
   *
   * @internal
   */
  private async resolveDeviceName(): Promise<string> {
    try {
      const info = await Device.getInfo();
      const model = info.model && info.model.trim() !== '' ? info.model : info.platform;
      return `${model}-${info.platform}`;
    } catch {
      const platformLabel = this.platform.isIOS ? 'iOS' : this.platform.isAndroid ? 'Android' : 'mobile';
      return `${platformLabel}-${Date.now()}`;
    }
  }

  /**
   * Persiste o token retornado pelo backend no storage adequado à plataforma.
   * No web é no-op (cookies HttpOnly cuidam da sessão).
   *
   * @internal
   */
  private persistToken(response: AuthResponse): Observable<AuthResponse> {
    const token = response.data?.token;
    if (token === undefined || token === '' || !this.platform.isMobile) {
      return of(response);
    }
    return from(this.authStorage.set(token)).pipe(
      tap(() => undefined),
      switchMap(() => of(response)),
    );
  }
}
