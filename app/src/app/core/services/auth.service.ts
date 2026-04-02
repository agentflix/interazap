import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';

/**
 * Resposta da API de autenticação contendo dados do usuario, plano e token.
 */
export interface AuthResponse {
  data: {
    user?: {
      id: string | number;
      name: string;
      email: string;
      avatar_url?: string | null;
      two_factor_enabled?: boolean;
    };
    tenant_plan?: {
      id: string;
      name: string;
      slug: string;
      ai_enabled: boolean;
    } | null;
    token?: string;
    permissions?: string[];
    requires_2fa?: boolean;
    two_factor_required?: boolean;
    email?: string;
  };
}

/**
 * Resposta da API de menu de navegacao.
 */
export interface MenuResponse {
  data: {
    menu: {
      label: string;
      icon: string;
      route: string;
      permission?: string;
      children?: {
        label: string;
        route: string;
        permission?: string;
      }[];
    }[];
  };
}

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

  /**
   * Autentica o usuario com email e senha.
   *
   * @param credentials - Objeto contendo email e password
   * @returns Observable com dados do usuario e token de acesso
   */
  login(credentials: { email: string; password: string }): Observable<AuthResponse> {
    return this.http.post<AuthResponse>(`${this.baseUrl}/auth/login`, credentials);
  }

  /**
   * Autentica o usuario com codigo de segundo fator (2FA).
   *
   * @param email - Email do usuario
   * @param code - Codigo de verificacao de 6 digitos
   * @returns Observable com dados do usuario e token de acesso
   */
  loginWith2FA(email: string, code: string): Observable<AuthResponse> {
    return this.http.post<AuthResponse>(`${this.baseUrl}/auth/login-with-2fa`, {
      email,
      code,
    });
  }

  /**
   * Encerra a sessao do usuario autenticado.
   *
   * @returns Observable vazio indicando sucesso
   */
  logout(): Observable<null> {
    return this.http.get<null>(`${this.baseUrl}/auth/logout`);
  }

  /**
   * Retorna os dados do usuario autenticado atualmente.
   *
   * @returns Observable com dados do usuario e plano vigente
   */
  me(): Observable<AuthResponse> {
    return this.http.get<AuthResponse>(`${this.baseUrl}/auth/me`);
  }

  /**
   * Recupera a estrutura do menu de navegacao para o usuario.
   *
   * @returns Observable com a arvore de menus
   */
  getMenu(): Observable<MenuResponse> {
    return this.http.get<MenuResponse>(`${this.baseUrl}/auth/get-menu`);
  }

  /**
   * Renova o token de acesso usando refresh token.
   *
   * @returns Observable com novos dados de autenticacao
   */
  refresh(): Observable<AuthResponse> {
    return this.http.post<AuthResponse>(`${this.baseUrl}/auth/refresh`, {});
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
    );
  }
}
