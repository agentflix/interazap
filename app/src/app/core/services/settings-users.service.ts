import { inject, Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';

export interface SettingsUser {
  id: string;
  name: string;
  email: string;
  is_active: boolean;
  is_primary_tenant_user?: boolean;
  created_at?: string | null;
  role?: string | null;
  roles?: string[];
}

export type SettingsUserStatus = 'all' | 'active' | 'inactive';

export interface PaginatedSettingsUsers {
  data: SettingsUser[];
  meta: {
    current_page: number;
    total: number;
    per_page: number;
    last_page: number;
  };
}

export interface CreateSettingsUserPayload {
  name: string;
  email: string;
  password: string;
  password_confirmation?: string;
  roles?: string[];
  role?: string | null;
  is_active?: boolean;
  tenant_id?: string;
}

export interface UpdateSettingsUserPayload {
  name: string;
  email: string;
  password?: string | null;
  password_confirmation?: string;
  roles?: string[];
  role?: string | null;
  is_active?: boolean;
  tenant_id?: string;
}

export interface SettingsUsersFilters {
  search?: string;
  status?: SettingsUserStatus;
  page?: number;
  per_page?: number;
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

/**
 * Serviço de usuários para o módulo de Configurações.
 *
 * Centraliza listagem, criação, atualização, remoção e ativação de usuários do tenant.
 */
@Injectable({ providedIn: 'root' })
export class SettingsUsersService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/auth/users`;

  /**
   * Listar usuários com filtros e paginação.
   */
  list(filters: SettingsUsersFilters = {}): Observable<PaginatedSettingsUsers> {
    let params = new HttpParams();
    if (filters.search) params = params.set('search', filters.search);
    if (filters.status && filters.status !== 'all') params = params.set('status', filters.status);
    if (filters.page) params = params.set('page', String(filters.page));
    if (filters.per_page) params = params.set('per_page', String(filters.per_page));
    return this.http.get<PaginatedSettingsUsers>(this.baseUrl, { params });
  }

  /**
   * Criar um novo usuário.
   */
  create(payload: CreateSettingsUserPayload): Observable<ApiResponse<{ user: SettingsUser }>> {
    return this.http.post<ApiResponse<{ user: SettingsUser }>>(this.baseUrl, payload);
  }

  /**
   * Atualizar um usuário existente.
   */
  update(
    id: string,
    payload: UpdateSettingsUserPayload,
  ): Observable<ApiResponse<{ user: SettingsUser }>> {
    return this.http.put<ApiResponse<{ user: SettingsUser }>>(`${this.baseUrl}/${id}`, payload);
  }

  /**
   * Alternar status ativo/inativo do usuário.
   */
  toggleStatus(id: string): Observable<ApiResponse<{ user: SettingsUser }>> {
    return this.http.post<ApiResponse<{ user: SettingsUser }>>(`${this.baseUrl}/${id}/toggle`, {});
  }

  /**
   * Excluir um usuário.
   */
  delete(id: string): Observable<ApiResponse<null>> {
    return this.http.delete<ApiResponse<null>>(`${this.baseUrl}/${id}`);
  }
}
