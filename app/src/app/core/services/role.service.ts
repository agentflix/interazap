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
   * Lists roles with optional search and pagination.
   *
   * @param filters - Optional filters (search, page, per_page)
   * @returns Observable with paginated roles list
   */
  list(filters: RoleFilters = {}): Observable<PaginatedRoles> {
    let params = new HttpParams();
    if (filters.search) params = params.set('search', filters.search);
    if (filters.page) params = params.set('page', String(filters.page));
    if (filters.per_page) params = params.set('per_page', String(filters.per_page));
    return this.http.get<PaginatedRoles>(this.baseUrl, { params });
  }

  /**
   * Retrieves a single role by ID.
   *
   * @param id - Role identifier
   * @returns Observable with role data
   */
  find(id: string): Observable<ApiResponse<Role>> {
    return this.http.get<ApiResponse<Role>>(`${this.baseUrl}/${id}`);
  }

  /**
   * Creates a new role with the given name and permissions.
   *
   * @param payload - Role creation data
   * @returns Observable with created role
   */
  create(payload: { name: string; permissions: string[] }): Observable<ApiResponse<Role>> {
    return this.http.post<ApiResponse<Role>>(this.baseUrl, payload);
  }

  /**
   * Updates an existing role's name and permissions.
   *
   * @param id - Role identifier
   * @param payload - Partial role data to update
   * @returns Observable with updated role
   */
  update(
    id: string,
    payload: { name: string; permissions: string[] },
  ): Observable<ApiResponse<Role>> {
    return this.http.put<ApiResponse<Role>>(`${this.baseUrl}/${id}`, payload);
  }

  /**
   * Deletes a role.
   *
   * @param id - Role identifier
   * @returns Observable completing on deletion
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
