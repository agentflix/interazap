import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { type Observable, map } from 'rxjs';
import { environment } from '@env/environment';
import type {
  Funnel,
  FunnelFilters,
  FunnelListResponse,
  FunnelResponse,
  FunnelPayload,
  FunnelStepPayload,
  FunnelStep,
} from '@core/models/funnel.model';

/**
 * Gerencia funis e etapas de funis do CRM com operações de CRUD e reordenação.
 */
@Injectable({ providedIn: 'root' })
export class FunnelService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/funnels`;

  // ─── Funnels CRUD ──────────────────────────────────────────────────────────

  /**
   * Lista funis com filtros e paginação.
   * @param filters Filtros opcionais: search, is_active, paginação, ordenação
   * @returns Observable com lista paginada de funis
   */
  list(filters: FunnelFilters = {}): Observable<FunnelListResponse> {
    let params = new HttpParams();
    params = this.appendTrimmedString(params, 'search', filters.search);
    params = this.appendBoolean(
      params,
      'is_active',
      typeof filters.is_active === 'boolean' ? filters.is_active : undefined,
    );
    params = this.appendNumber(params, 'per_page', filters.per_page);
    params = this.appendNumber(params, 'page', filters.page);
    params = this.appendTrimmedString(params, 'sort_by', filters.sort_by);
    params = this.appendTrimmedString(params, 'sort_dir', filters.sort_dir);
    return this.http.get<FunnelListResponse>(this.baseUrl, { params });
  }

  /**
   * Lista todos os funis sem paginação (para selects/dropdowns).
   * @returns Observable com array completo de funis
   */
  all(): Observable<{ data: { funnels: Funnel[] } }> {
    return this.http.get<{ data: Funnel[] | { funnels?: Funnel[] } }>(`${this.baseUrl}/all`).pipe(
      map((response) => {
        const data = response.data;
        const funnels = Array.isArray(data) ? data : (data?.funnels ?? []);
        return { data: { funnels } };
      }),
    );
  }

  /**
   * Retorna um funil pelo ID.
   * @param id Identificador do funil
   * @returns Observable com dados do funil
   */
  get(id: string | number): Observable<FunnelResponse> {
    return this.http.get<FunnelResponse>(`${this.baseUrl}/${id}`);
  }

  /**
   * Cria um novo funil no CRM.
   * @param payload Dados do funil (nome, descrição, etapas)
   * @returns Observable com o funil criado
   */
  create(payload: FunnelPayload): Observable<FunnelResponse> {
    return this.http.post<FunnelResponse>(this.baseUrl, payload);
  }

  /**
   * Atualiza um funil existente.
   * @param id Identificador do funil
   * @param payload Dados atualizados do funil
   * @returns Observable com o funil atualizado
   */
  update(id: string | number, payload: FunnelPayload): Observable<FunnelResponse> {
    return this.http.put<FunnelResponse>(`${this.baseUrl}/${id}`, payload);
  }

  /**
   * Exclui um funil do CRM.
   * @param id Identificador do funil
   * @returns Observable que completa após a exclusão
   */
  delete(id: string | number): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${id}`);
  }

  // ─── Funnel Steps CRUD ─────────────────────────────────────────────────────

  /**
   * Lista as etapas de um funil.
   * @param funnelId Identificador do funil
   * @returns Observable com array de etapas
   */
  listSteps(funnelId: string | number): Observable<{ data: { steps: FunnelStep[] } }> {
    return this.http.get<{ data: { steps: FunnelStep[] } }>(`${this.baseUrl}/${funnelId}/steps`);
  }

  /**
   * Cria uma nova etapa em um funil.
   * @param funnelId Identificador do funil
   * @param payload Dados da etapa (nome, ordem, cor)
   * @returns Observable com a etapa criada
   */
  createStep(
    funnelId: string | number,
    payload: FunnelStepPayload,
  ): Observable<{ data: { step: FunnelStep } }> {
    return this.http.post<{ data: { step: FunnelStep } }>(
      `${this.baseUrl}/${funnelId}/steps`,
      payload,
    );
  }

  /**
   * Atualiza uma etapa existente.
   * @param funnelId Identificador do funil
   * @param stepId Identificador da etapa
   * @param payload Dados atualizados da etapa
   * @returns Observable com a etapa atualizada
   */
  updateStep(
    funnelId: string | number,
    stepId: string | number,
    payload: FunnelStepPayload,
  ): Observable<{ data: { step: FunnelStep } }> {
    return this.http.put<{ data: { step: FunnelStep } }>(
      `${this.baseUrl}/${funnelId}/steps/${stepId}`,
      payload,
    );
  }

  /**
   * Exclui uma etapa de um funil.
   * @param funnelId Identificador do funil
   * @param stepId Identificador da etapa
   * @returns Observable que completa após a exclusão
   */
  deleteStep(funnelId: string | number, stepId: string | number): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${funnelId}/steps/${stepId}`);
  }

  /**
   * Reordena as etapas de um funil fornecendo a lista de IDs na ordem desejada.
   * @param funnelId Identificador do funil
   * @param stepIds Array de IDs de etapas na nova ordem
   * @returns Observable que completa após a reordenação
   */
  reorderSteps(funnelId: string | number, stepIds: (string | number)[]): Observable<null> {
    const steps = stepIds.map((id, index) => ({ id, order: index + 1 }));
    return this.http.post<null>(`${this.baseUrl}/${funnelId}/steps/reorder`, { steps });
  }

  private appendTrimmedString(params: HttpParams, key: string, value?: string): HttpParams {
    if (value === undefined) return params;
    const trimmed = value.trim();
    if (trimmed.length === 0) return params;
    return params.set(key, trimmed);
  }

  private appendBoolean(params: HttpParams, key: string, value?: boolean): HttpParams {
    if (value === undefined) return params;
    return params.set(key, String(value));
  }

  private appendNumber(params: HttpParams, key: string, value?: number): HttpParams {
    if (value === undefined) return params;
    return params.set(key, String(value));
  }
}
