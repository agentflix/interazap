import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import type { InstanceFilters, InstanceListResponse } from '@core/models/instance.model';
export type { Instance, InstanceFilters, InstanceListResponse, InstanceStatus } from '@core/models/instance.model';



/**
 * Serviço para gestão de instâncias de integração (WhatsApp, etc).
 *
 * Permite listar e filtrar as instâncias configuradas para o tenant atual,
 * fornecendo informações de status de conexão e configurações específicas.
 *
 * @class InstanceService
 * @description Service para acesso aos dados de instâncias de comunicação.
 */
@Injectable({ providedIn: 'root' })
export class InstanceService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/channels`;

  /**
   * Obtém a lista paginada de instâncias cadastradas.
   * @param filters Filtros de busca (nome, integração, status, atividade).
   * @returns {Observable<InstanceListResponse>} Stream finito com a lista de instâncias.
   */
  list(filters: InstanceFilters = {}): Observable<InstanceListResponse> {
    let params = new HttpParams();
    if (filters.search) params = params.set('search', filters.search);
    if (filters.integration_id)
      params = params.set('integration_id', String(filters.integration_id));
    if (filters.status) params = params.set('status', filters.status);
    if (typeof filters.is_active === 'boolean') {
      params = params.set('is_active', String(filters.is_active));
    }
    if (filters.per_page) params = params.set('per_page', String(filters.per_page));
    if (filters.page) params = params.set('page', String(filters.page));
    return this.http.get<InstanceListResponse>(this.baseUrl, { params });
  }
}
