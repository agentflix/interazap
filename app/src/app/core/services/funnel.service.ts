import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '@env/environment';
import { type PaginatedResponse } from '@core/models/pagination.model';
import type { Funnel, FunnelFilters, FunnelPayload, FunnelStep, FunnelStepPayload } from '@core/models/funnel.model';
export type { Funnel, FunnelFilters, FunnelPayload, FunnelStep, FunnelStepPayload } from '@core/models/funnel.model';



/**
 * Gerencia funis de vendas e suas etapas no CRM.
 *
 * @remarks
 * Fornece operações de CRUD para funis e etapas de pipeline,
 * incluindo suporte a reordenação em lote.
 *
 * @example
 * ```typescript
 * const service = inject(FunnelService);
 * service.list({ is_active: true }).subscribe();
 * ```
 */
@Injectable({ providedIn: 'root' })
export class FunnelService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/funnels`;

  // ─────────────────────────────────────────────────────────────────────────────
  // Funnels CRUD
  // ─────────────────────────────────────────────────────────────────────────────

  /**
   * Lista funis com filtros e paginação opcionais.
   *
   * @param filters - Filtros opcionais (busca, is_active, paginação)
   * @returns Observable com lista paginada de funis
   */
  list(filters: FunnelFilters = {}): Observable<PaginatedResponse<Funnel>> {
    let params = new HttpParams();

    if (filters.search !== undefined && filters.search.trim() !== '') {
      params = params.set('search', filters.search);
    }
    if (filters.is_active !== undefined && filters.is_active !== 'all') {
      params = params.set(
        'is_active',
        String(filters.is_active === true || filters.is_active === 'active'),
      );
    }
    if (filters.per_page !== undefined) params = params.set('per_page', String(filters.per_page));
    if (filters.page !== undefined) params = params.set('page', String(filters.page));

    return this.http.get<PaginatedResponse<Funnel>>(this.baseUrl, { params });
  }

  /**
   * Retorna todos os funis sem paginação (para dropdowns/selects).
   *
   * @returns Observable com todos os funis encapsulados no objeto data
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
   * Retorna um único funil pelo ID.
   *
   * @param id - Identificador do funil
   * @returns Observable com os dados do funil
   */
  get(id: string | number): Observable<{ data: { funnel: Funnel } }> {
    return this.http.get<{ data: { funnel: Funnel } }>(`${this.baseUrl}/${id}`);
  }

  /**
   * Cria um novo funil.
   *
   * @param payload - Dados do funil (nome, descrição, is_active, etapas)
   * @returns Observable com o funil criado
   */
  create(payload: FunnelPayload): Observable<{ data: { funnel: Funnel } }> {
    return this.http.post<{ data: { funnel: Funnel } }>(this.baseUrl, payload);
  }

  /**
   * Atualiza um funil existente.
   *
   * @param id - Identificador do funil
   * @param payload - Dados parciais do funil para atualização
   * @returns Observable com o funil atualizado
   */
  update(id: string | number, payload: FunnelPayload): Observable<{ data: { funnel: Funnel } }> {
    return this.http.put<{ data: { funnel: Funnel } }>(`${this.baseUrl}/${id}`, payload);
  }

  /**
   * Exclui um funil.
   *
   * @param id - Identificador do funil
   * @returns Observable que completa após a exclusão
   */
  delete(id: string | number): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${id}`);
  }

  // ─────────────────────────────────────────────────────────────────────────────
  // Funnel Steps CRUD
  // ─────────────────────────────────────────────────────────────────────────────

  /**
   * Lista todas as etapas de um funil específico.
   *
   * @param funnelId - Identificador do funil pai
   * @returns Observable com array de etapas
   */
  listSteps(funnelId: string | number): Observable<{ data: { steps: FunnelStep[] } }> {
    return this.http.get<{ data: { steps: FunnelStep[] } }>(`${this.baseUrl}/${funnelId}/steps`);
  }

  /**
   * Cria uma nova etapa dentro de um funil.
   *
   * @param funnelId - Identificador do funil pai
   * @param payload - Dados da etapa (nome, ordem, cor, is_active)
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
   * Atualiza uma etapa existente de um funil.
   *
   * @param funnelId - Identificador do funil pai
   * @param stepId - Identificador da etapa
   * @param payload - Dados parciais da etapa para atualização
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
   *
   * @param funnelId - Identificador do funil pai
   * @param stepId - Identificador da etapa
   * @returns Observable que completa após a exclusão
   */
  deleteStep(funnelId: string | number, stepId: string | number): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${funnelId}/steps/${stepId}`);
  }

  /**
   * Reordena as etapas de um funil fornecendo a lista de IDs na ordem desejada.
   *
   * @param funnelId - Identificador do funil pai
   * @param stepIds - Array de IDs de etapas na ordem desejada
   * @returns Observable que completa após a reordenação
   */
  reorderSteps(funnelId: string | number, stepIds: (string | number)[]): Observable<null> {
    const steps = stepIds.map((id, index) => ({ id, order: index + 1 }));
    return this.http.post<null>(`${this.baseUrl}/${funnelId}/steps/reorder`, { steps });
  }
}
