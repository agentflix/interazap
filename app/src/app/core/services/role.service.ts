import { inject, Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import {
  type ApiResponse,
  type GroupedPermissions,
  type PaginatedRoleUsers,
  type PaginatedRoles,
  type Role,
  type RoleFilters,
  type RoleUsersFilters,
} from '../models/role.model';

/**
 * Serviço para gestão de perfis de acesso (roles).
 */
@Injectable({ providedIn: 'root' })
export class RoleService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/auth/roles`;

  /**
   * Lista roles com busca e paginação opcionais.
   *
   * @param filters - Filtros opcionais: search, page, per_page
   * @returns Observable com lista paginada de roles
   */
  list(filters: RoleFilters = {}): Observable<PaginatedRoles> {
    let params = new HttpParams();
    if (filters.search) params = params.set('search', filters.search);
    if (filters.page) params = params.set('page', String(filters.page));
    if (filters.per_page) params = params.set('per_page', String(filters.per_page));
    return this.http.get<PaginatedRoles>(this.baseUrl, { params });
  }

  /**
   * Retorna uma role pelo ID.
   *
   * @param id - Identificador da role
   * @returns Observable com dados da role
   */
  find(id: string): Observable<ApiResponse<Role>> {
    return this.http.get<ApiResponse<Role>>(`${this.baseUrl}/${id}`);
  }

  /**
   * Cria uma nova role com nome e permissões.
   *
   * @param payload - Dados de criação da role
   * @returns Observable com a role criada
   */
  create(payload: { name: string; permissions: string[] }): Observable<ApiResponse<Role>> {
    return this.http.post<ApiResponse<Role>>(this.baseUrl, payload);
  }

  /**
   * Atualiza nome e permissões de uma role existente.
   *
   * @param id - Identificador da role
   * @param payload - Dados parciais para atualização
   * @returns Observable com a role atualizada
   */
  update(
    id: string,
    payload: { name: string; permissions: string[] },
  ): Observable<ApiResponse<Role>> {
    return this.http.put<ApiResponse<Role>>(`${this.baseUrl}/${id}`, payload);
  }

  /**
   * Exclui uma role.
   *
   * @param id - Identificador da role
   * @returns Observable que completa após a exclusão
   */
  delete(id: string): Observable<ApiResponse<null>> {
    return this.http.delete<ApiResponse<null>>(`${this.baseUrl}/${id}`);
  }

  /**
   * Retorna todas as permissões disponíveis agrupadas por módulo.
   */
  permissions(): Observable<ApiResponse<{ permissions: GroupedPermissions }>> {
    return this.http.get<ApiResponse<{ permissions: GroupedPermissions }>>(
      `${this.baseUrl}/permissions`,
    );
  }

  /**
   * Lista simples de roles (para selects/dropdowns).
   */
  listAll(): Observable<PaginatedRoles> {
    return this.list({ per_page: 100 });
  }

  /**
   * Lista usuários que possuem uma role específica.
   *
   * @param roleId - UUID da role
   * @param filters - Filtros opcionais (search, page, per_page)
   * @returns Observable com lista paginada de usuários da role
   */
  listUsersByRole(roleId: string, filters: RoleUsersFilters = {}): Observable<PaginatedRoleUsers> {
    let params = new HttpParams();
    if (filters.search) params = params.set('search', filters.search);
    if (filters.page) params = params.set('page', String(filters.page));
    if (filters.per_page) params = params.set('per_page', String(filters.per_page));
    return this.http.get<PaginatedRoleUsers>(`${this.baseUrl}/${roleId}/users`, { params });
  }
}
