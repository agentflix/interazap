import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import { type PaginatedResponse } from '@core/models/pagination.model';
import { type Funnel, type FunnelStep } from './funnel.service';
import type { KanbanStepPage, Negotiation, NegotiationFilters, NegotiationKanbanFilters, NegotiationKanbanStep, NegotiationPayload, NegotiationStatus } from '@core/models/negotiation.model';
export type { KanbanStepPage, Negotiation, NegotiationCompanySummary, NegotiationContactSummary, NegotiationFilters, NegotiationKanbanFilters, NegotiationKanbanStep, NegotiationPayload, NegotiationStatus, NegotiationUserSummary } from '@core/models/negotiation.model';



interface NegotiationUpdateRequestBody {
  title?: string;
  amount?: number;
  crm_negotiation_funnel_id?: string | number;
  crm_negotiation_funnel_step_id?: string | number;
  crm_contact_id?: string | number;
  crm_company_id?: string | number;
  auth_user_id?: string | number;
  expected_close?: string;
  notes?: string;
  status?: NegotiationStatus;
}


/** Resposta da API para uma página de cursor de uma etapa. */

/**
 * Servico responsavel pelo gerenciamento completo de negociacoes no CRM.
 * Suporta CRUD, visualizacao em Kanban, movimentacao entre etapas,
 * marcao de ganho/perda e resumo estatistico.
 *
 * @class NegotiationService
 * @example
 * ```ts
 * const negotiationSvc = inject(NegotiationService);
 * negotiationSvc.list({ status: 'open', funnel_id: 1 }).subscribe();
 * negotiationSvc.kanban(1, {}).subscribe();
 * ```
 */
@Injectable({ providedIn: 'root' })
export class NegotiationService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/crm/negotiations`;

  /**
   * Lista negociacoes com filtros avancados e paginacao.
   *
   * @param filters - Filtros: search, status, funnel_id, step_id, crm_company_id,
   *   contact_id, user_id, date_from, date_to, amount_min/max, tag_ids, etc.
   * @returns Observable com lista paginada de negociacoes
   */
  list(filters: NegotiationFilters = {}): Observable<PaginatedResponse<Negotiation>> {
    let params = this.buildNegotiationParams(filters);
    params = this.appendScalar(params, 'per_page', filters.per_page);
    params = this.appendScalar(params, 'page', filters.page);

    return this.http.get<PaginatedResponse<Negotiation>>(this.baseUrl, {
      params,
    });
  }

  /**
   * Obtem detalhes de uma negociacao especifica.
   *
   * @param id - Identificador da negociacao
   * @returns Observable com dados da negociacao
   */
  get(id: string | number): Observable<{ data: { negotiation: Negotiation } }> {
    return this.http.get<{ data: { negotiation: Negotiation } }>(`${this.baseUrl}/${id}`);
  }

  /**
   * Cria uma nova negociacao.
   *
   * @param payload - Dados da negociacao (title, funnel_id, step_id, contact_id, etc)
   * @returns Observable com a negociacao criada
   */
  create(payload: NegotiationPayload): Observable<{ data: { negotiation: Negotiation } }> {
    const body = {
      title: payload.title,
      crm_negotiation_funnel_id: payload.funnel_id,
      crm_negotiation_funnel_step_id: payload.step_id,
      crm_contact_id: payload.contact_id,
      crm_company_id: payload.crm_company_id,
      auth_user_id: payload.user_id,
      expected_close: payload.expected_close_date,
      notes: payload.notes,
      status: payload.status,
    };
    // user_id is handled by backend auth context or ignored if not in rules
    return this.http.post<{ data: { negotiation: Negotiation } }>(this.baseUrl, body);
  }

  /**
   * Atualiza uma negociacao existente.
   *
   * @param id - Identificador da negociacao
   * @param payload - Dados a serem atualizados
   * @returns Observable com a negociacao atualizada
   */
  update(
    id: string | number,
    payload: Partial<NegotiationPayload>,
  ): Observable<{ data: { negotiation: Negotiation } }> {
    const body: NegotiationUpdateRequestBody = {};
    this.assignIfDefined(body, 'title', payload.title);
    this.assignIfDefined(body, 'amount', payload.value);
    this.assignIfDefined(body, 'crm_negotiation_funnel_id', payload.funnel_id);
    this.assignIfDefined(body, 'crm_negotiation_funnel_step_id', payload.step_id);
    this.assignIfDefined(body, 'crm_contact_id', payload.contact_id);
    this.assignIfDefined(body, 'crm_company_id', payload.crm_company_id);
    this.assignIfDefined(body, 'auth_user_id', payload.user_id);
    this.assignIfDefined(body, 'expected_close', payload.expected_close_date);
    this.assignIfDefined(body, 'notes', payload.notes);
    this.assignIfDefined(body, 'status', payload.status);

    return this.http.put<{ data: { negotiation: Negotiation } }>(`${this.baseUrl}/${id}`, body);
  }

  /**
   * Remove uma negociacao.
   *
   * @param id - Identificador da negociacao
   * @returns Observable vazio indicando sucesso
   */
  delete(id: string | number): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/${id}`);
  }

  /**
   * Retorna a estrutura Kanban de um funil com negociacoes organizadas por etapa.
   *
   * @param funnelId - Identificador do funil
   * @param filters - Filtros adicionais (status, search, etc)
   * @returns Observable com funil e etapas contendo negociacoes
   */
  kanban(
    funnelId: string | number,
    filters: NegotiationKanbanFilters = {},
  ): Observable<{ data: { funnel: Funnel; steps: NegotiationKanbanStep[] } }> {
    let params = this.buildNegotiationParams(filters);
    params = this.appendScalar(params, 'funnel_id', funnelId);

    return this.http.get<{
      data: { funnel: Funnel; steps: NegotiationKanbanStep[] };
    }>(`${environment.apiUrl}/crm/negotiations-kanban`, {
      params,
    });
  }

  /**
   * Busca a proxima pagina de negociacoes de uma etapa do kanban via cursor pagination.
   *
   * @param stepId - Identificador da etapa
   * @param funnelId - Identificador do funil
   * @param cursor - Cursor opaco da pagina anterior (null = primeira pagina)
   * @param filters - Filtros adicionais
   * @returns Observable com negociacoes paginadas e cursor para proxima pagina
   */
  kanbanStep(
    stepId: string,
    funnelId: string,
    cursor: string | null = null,
    filters: NegotiationKanbanFilters = {},
  ): Observable<{ data: KanbanStepPage }> {
    let params = this.buildNegotiationParams(filters);
    params = this.appendScalar(params, 'funnel_id', funnelId);
    if (cursor) {
      params = this.appendScalar(params, 'cursor', cursor);
    }

    return this.http.get<{ data: KanbanStepPage }>(
      `${environment.apiUrl}/crm/negotiations-kanban/step/${stepId}`,
      { params },
    );
  }

  /**
   * Move uma negociacao para outra etapa do funil e define sua posicao.
   *
   * @param id - Identificador da negociacao
   * @param stepId - Identificador da nova etapa
   * @param position - Posicao dentro da etapa (0-based)
   * @returns Observable com a negociacao atualizada
   */
  move(
    id: string | number,
    stepId: string | number,
    position: number,
  ): Observable<{ data: { negotiation: Negotiation } }> {
    return this.http.post<{ data: { negotiation: Negotiation } }>(`${this.baseUrl}/${id}/move`, {
      crm_negotiation_funnel_step_id: stepId,
      position,
    });
  }

  /**
   * Marca uma negociacao como ganha (status = 'won').
   *
   * @param id - Identificador da negociacao
   * @returns Observable com a negociacao atualizada
   */
  markAsWon(id: string | number): Observable<{ data: { negotiation: Negotiation } }> {
    return this.http.post<{ data: { negotiation: Negotiation } }>(`${this.baseUrl}/${id}/won`, {});
  }

  /**
   * Marca uma negociacao como perdida, opcionalmente informando motivo.
   *
   * @param id - Identificador da negociacao
   * @param reasonLossId - ID do motivo de perda (opcional)
   * @param lossComment - Comentario sobre a perda (opcional)
   * @returns Observable com a negociacao atualizada
   */
  markAsLost(
    id: string | number,
    reasonLossId?: string | number,
    lossComment?: string,
  ): Observable<{ data: { negotiation: Negotiation } }> {
    return this.http.post<{ data: { negotiation: Negotiation } }>(`${this.baseUrl}/${id}/lost`, {
      crm_reason_loss_id: reasonLossId,
      loss_comment: lossComment,
    });
  }

  /**
   * Reabre uma negociacao perdida ou ganha, voltando para status 'open'.
   *
   * @param id - Identificador da negociacao
   * @returns Observable com a negociacao atualizada
   */
  reopen(id: string | number): Observable<{ data: { negotiation: Negotiation } }> {
    return this.http.post<{ data: { negotiation: Negotiation } }>(
      `${this.baseUrl}/${id}/reopen`,
      {},
    );
  }

  /**
   * Retorna um resumo/agregacao de negociacoes conforme filtros aplicados.
   *
   * @param filters - Filtros para agregacao (status, funnel, user, datas)
   * @returns Observable com dados agregados (totais, medias, etc)
   */
  summary(filters: NegotiationFilters = {}): Observable<{ data: Record<string, unknown> }> {
    let params = new HttpParams();
    params = this.appendStatus(params, filters.status);
    params = this.appendScalar(params, 'funnel_id', filters.funnel_id);
    params = this.appendScalar(params, 'step_id', filters.step_id);
    params = this.appendScalar(params, 'user_id', filters.user_id);
    params = this.appendTrimmedString(params, 'date_from', filters.date_from);
    params = this.appendTrimmedString(params, 'date_to', filters.date_to);

    return this.http.get<{ data: Record<string, unknown> }>(`${this.baseUrl}/summary`, { params });
  }

  private buildNegotiationParams(
    filters: NegotiationFilters | NegotiationKanbanFilters,
  ): HttpParams {
    let params = new HttpParams();
    params = this.appendTrimmedString(params, 'search', filters.search);
    params = this.appendStatus(params, filters.status);
    params = this.appendScalar(params, 'funnel_id', filters.funnel_id);
    params = this.appendScalar(params, 'step_id', filters.step_id);
    params = this.appendScalar(params, 'crm_company_id', filters.crm_company_id);
    params = this.appendScalar(params, 'contact_id', filters.contact_id);
    params = this.appendScalar(params, 'user_id', filters.user_id);
    params = this.appendTrimmedString(params, 'date_from', filters.date_from);
    params = this.appendTrimmedString(params, 'date_to', filters.date_to);
    params = this.appendTrimmedString(params, 'expected_close_from', filters.expected_close_from);
    params = this.appendTrimmedString(params, 'expected_close_to', filters.expected_close_to);
    params = this.appendScalar(params, 'amount_min', filters.amount_min);
    params = this.appendScalar(params, 'amount_max', filters.amount_max);
    params = this.appendScalar(params, 'lead_score_min', filters.lead_score_min);
    params = this.appendScalar(params, 'lead_score_max', filters.lead_score_max);
    params = this.appendScalar(params, 'reason_loss_id', filters.reason_loss_id);
    params = this.appendBoolean(params, 'has_pending_tasks', filters.has_pending_tasks);
    params = this.appendScalar(params, 'product_id', filters.product_id);
    params = this.appendArray(params, 'tag_ids[]', filters.tag_ids);
    return params;
  }

  private appendTrimmedString(params: HttpParams, key: string, value?: string): HttpParams {
    if (value === undefined) return params;
    const trimmed = value.trim();
    if (trimmed.length === 0) return params;
    return params.set(key, trimmed);
  }

  private appendStatus(params: HttpParams, value?: NegotiationStatus | string | null): HttpParams {
    if (value === undefined || value === null) return params;
    const normalized = String(value).trim();
    if (normalized.length === 0) return params;
    return params.set('status', normalized);
  }

  private appendScalar(
    params: HttpParams,
    key: string,
    value?: string | number | null,
  ): HttpParams {
    if (value === undefined || value === null) return params;
    return params.set(key, String(value));
  }

  private appendBoolean(params: HttpParams, key: string, value?: boolean): HttpParams {
    if (value === undefined) return params;
    return params.set(key, value ? 'true' : 'false');
  }

  private appendArray(params: HttpParams, key: string, values?: (string | number)[]): HttpParams {
    if (values === undefined || values.length === 0) return params;
    return values.reduce((acc, value) => acc.append(key, String(value)), params);
  }

  private assignIfDefined<K extends keyof NegotiationUpdateRequestBody>(
    body: NegotiationUpdateRequestBody,
    key: K,
    value: NegotiationUpdateRequestBody[K] | undefined,
  ): void {
    if (value !== undefined) {
      body[key] = value;
    }
  }
}
