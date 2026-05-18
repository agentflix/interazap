import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import type { PlatformUser, PlatformUserFilters, PlatformUserListResponse } from '@core/models/platform-user.model';
export type { PlatformUser, PlatformUserFilters, PlatformUserListResponse } from '@core/models/platform-user.model';



/**
 * Servico responsavel pelo gerenciamento de usuarios da plataforma.
 * Diferente de UserService (por tenant), este gerencia usuarios em nivel de plataforma.
 *
 * @class PlatformUserService
 * @example
 * ```ts
 * const svc = inject(PlatformUserService);
 * svc.list({ tenant_id: '123', is_active: true }).subscribe();
 * ```
 */
@Injectable({
  providedIn: 'root',
})
export class PlatformUserService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = `${environment.apiUrl}/platform/users`;

  /**
   * Lista usuarios da plataforma com suporte a filtros e paginacao.
   *
   * @param params - Filtros: search, is_active, tenant_id, page, per_page, sort_by, sort_dir
   * @returns Observable com lista paginada de usuarios da plataforma
   */
  list(params: PlatformUserFilters = {}): Observable<PlatformUserListResponse> {
    let httpParams = new HttpParams();
    Object.keys(params).forEach((key) => {
      const value = (params as Record<string, string | number | boolean | undefined>)[key];
      if (value !== undefined && value !== null) {
        httpParams = httpParams.set(key, String(value));
      }
    });
    return this.http.get<PlatformUserListResponse>(this.apiUrl, { params: httpParams });
  }

  /**
   * Obtem detalhes de um usuario da plataforma.
   *
   * @param id - Identificador do usuario
   * @returns Observable com dados do usuario
   */
  show(id: string): Observable<{ data: PlatformUser }> {
    return this.http.get<{ data: PlatformUser }>(`${this.apiUrl}/${id}`);
  }

  /**
   * Remove um usuario da plataforma.
   *
   * @param id - Identificador do usuario
   * @returns Observable vazio indicando sucesso
   */
  delete(id: string): Observable<null> {
    return this.http.delete<null>(`${this.apiUrl}/${id}`);
  }

  /**
   * Cria um novo usuario na plataforma.
   *
   * @param data - Dados do usuario incluindo password e password_confirmation
   * @returns Observable com o usuario criado
   */
  create(
    data: Partial<PlatformUser> & { password?: string; password_confirmation?: string },
  ): Observable<{ data: PlatformUser }> {
    return this.http.post<{ data: PlatformUser }>(this.apiUrl, data);
  }

  /**
   * Atualiza um usuario existente da plataforma.
   *
   * @param id - Identificador do usuario
   * @param data - Dados a serem atualizados
   * @returns Observable com o usuario atualizado
   */
  update(
    id: string,
    data: Partial<PlatformUser> & { password?: string; password_confirmation?: string },
  ): Observable<{ data: PlatformUser }> {
    return this.http.put<{ data: PlatformUser }>(`${this.apiUrl}/${id}`, data);
  }
}
