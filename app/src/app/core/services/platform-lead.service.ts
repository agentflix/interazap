import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { environment } from '@env/environment';
import { type Observable } from 'rxjs';
import type { PlatformLead, PlatformLeadConvertPayload, PlatformLeadFilters, PlatformLeadListResponse } from '@core/models/platform-lead.model';
export type { PlatformLead, PlatformLeadConvertPayload, PlatformLeadFilters, PlatformLeadListResponse } from '@core/models/platform-lead.model';



interface ApiDataResponse<T> {
  success: boolean;
  message: string;
  data: T;
}


/**
 * Gerencia leads da plataforma com operações de listagem, conversão, busca e exportação.
 *
 * Contexto: service HTTP para admin de plataforma, endpoints em /platform/leads.
 */
@Injectable({ providedIn: 'root' })
export class PlatformLeadService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = `${environment.apiUrl}/platform/leads`;

  /**
   * Lista leads da plataforma com filtros e paginação.
   *
   * @param filters - Filtros: search, status, tenant_id, page, per_page
   * @returns Observable com lista paginada de leads
   */
  list(filters: PlatformLeadFilters = {}): Observable<PlatformLeadListResponse> {
    let params = new HttpParams();

    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && String(value).trim() !== '') {
        params = params.set(key, String(value));
      }
    });

    return this.http.get<PlatformLeadListResponse>(this.apiUrl, { params });
  }

  /**
   * Converte um lead em contato/oportunidade.
   *
   * @param id - Identificador do lead
   * @param payload - Dados para conversão: funnel_id, step_id, etc.
   * @returns Observable com o lead atualizado
   */
  convert(
    id: string,
    payload: PlatformLeadConvertPayload,
  ): Observable<ApiDataResponse<PlatformLead>> {
    return this.http.post<ApiDataResponse<PlatformLead>>(`${this.apiUrl}/${id}/convert`, payload);
  }

  /**
   * Busca um lead específico pelo ID.
   *
   * @param id - Identificador do lead
   * @returns Observable com os dados do lead
   */
  find(id: string): Observable<{ data: PlatformLead }> {
    return this.http.get<{ data: PlatformLead }>(`${this.apiUrl}/${id}`);
  }

  /**
   * Exporta leads filtrados como arquivo CSV/XLSX.
   *
   * @param filters - Filtros aplicados à exportação
   * @returns Observable com o blob do arquivo exportado
   */
  export(filters: PlatformLeadFilters = {}): Observable<Blob> {
    let params = new HttpParams();

    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && String(value).trim() !== '') {
        params = params.set(key, String(value));
      }
    });

    return this.http.get(`${this.apiUrl}/export`, {
      params,
      responseType: 'blob',
    });
  }
}
