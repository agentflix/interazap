import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import {
  type User,
  type UserFilters,
  type UserListResponse,
  type UserUpsertPayload,
} from '@core/models/user.model';

/**
 * Servico responsavel pelo CRUD de usuarios da plataforma.
 * Gerencia usuarios dentro de um tenant (usuarios da aplicacao).
 *
 * @class UserService
 * @example
 * ```ts
 * const userService = inject(UserService);
 * userService.list({ search: 'John', is_active: true }).subscribe();
 * ```
 */
@Injectable({
  providedIn: 'root',
})
export class UserService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = `${environment.apiUrl}/auth/users`;

  /**
   * Lista usuarios com suporte a filtros e paginacao.
   *
   * @param params - Filtros de busca: search, is_active, page, per_page, sort_by, sort_dir
   * @returns Observable com lista paginada de usuarios
   */
  list(params: UserFilters = {}): Observable<UserListResponse> {
    let httpParams = new HttpParams();
    Object.keys(params).forEach((key) => {
      const value = (params as Record<string, string | number | boolean | undefined>)[key];
      if (value !== undefined && value !== null) {
        httpParams = httpParams.set(key, String(value));
      }
    });
    return this.http.get<UserListResponse>(this.apiUrl, { params: httpParams });
  }

  /**
   * Obtem detalhes de um usuario especifico.
   *
   * @param id - Identificador unico do usuario
   * @returns Observable com dados do usuario
   */
  show(id: string): Observable<{ data: User }> {
    return this.http.get<{ data: User }>(`${this.apiUrl}/${id}`);
  }

  /**
   * Cria um novo usuario.
   *
   * @param data - Dados do usuario (name, email, password, role, etc)
   * @returns Observable com o usuario criado
   */
  create(data: UserUpsertPayload): Observable<{ data: User }> {
    return this.http.post<{ data: User }>(this.apiUrl, data);
  }

  /**
   * Atualiza um usuario existente.
   *
   * @param id - Identificador do usuario
   * @param data - Dados a serem atualizados
   * @returns Observable com o usuario atualizado
   */
  update(id: string, data: UserUpsertPayload): Observable<{ data: User }> {
    return this.http.put<{ data: User }>(`${this.apiUrl}/${id}`, data);
  }

  /**
   * Remove um usuario do sistema.
   *
   * @param id - Identificador do usuario
   * @returns Observable vazio indicando sucesso
   */
  delete(id: string): Observable<null> {
    return this.http.delete<null>(`${this.apiUrl}/${id}`);
  }

  /**
   * Alterna o status ativo/inativo de um usuario.
   *
   * @param id - Identificador do usuario
   * @returns Observable com o usuario com status alterado
   */
  toggleActive(id: string): Observable<{ data: User }> {
    return this.http.post<{ data: User }>(`${this.apiUrl}/${id}/toggle`, {});
  }

  /**
   * Remove uma role específica de um usuário.
   *
   * @param id - Identificador do usuario
   * @param roleName - Nome da role a remover
   * @returns Observable com o usuario atualizado
   */
  removeRole(id: string, roleName: string): Observable<{ data: User }> {
    return this.http.post<{ data: User }>(`${this.apiUrl}/${id}/roles/remove`, { role: roleName });
  }
}
