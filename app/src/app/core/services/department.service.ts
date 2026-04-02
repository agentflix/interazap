import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';

export interface Department {
  id: string;
  name: string;
  description?: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface DepartmentResponse {
  success: boolean;
  message?: string;
  data: Department;
}

export interface DepartmentListResponse {
  success: boolean;
  data: Department[];
  meta: {
    current_page: number;
    from: number;
    last_page: number;
    per_page: number;
    to: number;
    total: number;
  };
}

export interface DepartmentFilters {
  search?: string;
  is_active?: boolean;
  per_page?: number;
  page?: number;
  sort_by?: string;
  sort_dir?: string;
}

@Injectable({
  providedIn: 'root',
})
export class DepartmentService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = `${environment.apiUrl}/crm/departments`;

  /**
   * Listar departamentos com filtros e paginação.
   * @param params Filtros de listagem.
   */
  list(params: DepartmentFilters): Observable<DepartmentListResponse> {
    let httpParams = new HttpParams();
    Object.keys(params).forEach((key) => {
      const value = (params as Record<string, unknown>)[key];
      if (value !== undefined && value !== null) {
        httpParams = httpParams.set(key, String(value));
      }
    });
    return this.http.get<DepartmentListResponse>(this.apiUrl, { params: httpParams });
  }

  /**
   * Listar todos os departamentos sem paginação.
   */
  all(): Observable<{ success: boolean; data: { departments: Department[] } }> {
    return this.http.get<{ success: boolean; data: { departments: Department[] } }>(
      `${this.apiUrl}/all`,
    );
  }

  /**
   * Obter detalhes de um departamento.
   * @param id Identificador do departamento.
   */
  show(id: string): Observable<DepartmentResponse> {
    return this.http.get<DepartmentResponse>(`${this.apiUrl}/${id}`);
  }

  /**
   * Criar um novo departamento.
   * @param data Dados do departamento.
   */
  create(data: Partial<Department>): Observable<DepartmentResponse> {
    return this.http.post<DepartmentResponse>(this.apiUrl, data);
  }

  /**
   * Atualizar um departamento.
   * @param id Identificador do departamento.
   * @param data Dados atualizados.
   */
  update(id: string, data: Partial<Department>): Observable<DepartmentResponse> {
    return this.http.put<DepartmentResponse>(`${this.apiUrl}/${id}`, data);
  }

  /**
   * Remover um departamento.
   * @param id Identificador do departamento.
   */
  delete(id: string): Observable<null> {
    return this.http.delete<null>(`${this.apiUrl}/${id}`);
  }

  /**
   * Alternar status ativo/inativo do departamento.
   * @param id Identificador do departamento.
   */
  toggleActive(id: string): Observable<DepartmentResponse> {
    return this.http.patch<DepartmentResponse>(`${this.apiUrl}/${id}/toggle-active`, {});
  }
}
