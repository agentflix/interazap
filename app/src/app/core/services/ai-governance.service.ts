import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { type Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { environment } from '@env/environment';
import { type Paginated } from '@shared/types/pagination';
import {
  type MasterPrompt,
  type MasterPromptPayload,
  type SegmentPrompt,
  type SegmentPromptPayload,
  type PlanPrompt,
  type PlanPromptPayload,
  type QuarantinedPrompt,
} from '../../pages/ai/models/ai.model';

/** Single-item API response wrapper. */
interface ApiDataResponse<T> {
  data: T;
}

/** Shape returned by the prompt resolve preview endpoint. */
interface PromptResolveResponse {
  resolved_prompt: string;
  components: {
    master: string;
    segment: string;
    plan: string;
    tenant: string;
  };
}

/**
 * Gerencia endpoints de governança de prompts de IA para o super admin da plataforma.
 *
 * Fornece CRUD para prompts master, segmento e plano, além de aprovação/rejeição
 * de prompts em quarentena e preview de resolução de prompt composto.
 */
@Injectable({ providedIn: 'root' })
export class AiGovernanceService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/platform/ai/prompts`;

  /**
   * Lista prompts master com filtros opcionais de busca e paginação.
   * @param filters Filtros opcionais: search, page
   * @returns Observable com lista paginada de prompts master
   */
  listMasters(filters?: { search?: string; page?: number }): Observable<Paginated<MasterPrompt>> {
    const params = new URLSearchParams();
    if (filters?.search) params.set('search', filters.search);
    if (filters?.page) params.set('page', String(filters.page));

    const query = params.toString();
    return this.http.get<Paginated<MasterPrompt>>(
      `${this.baseUrl}/masters${query ? `?${query}` : ''}`,
    );
  }

  /** Retorna um prompt master pelo ID. */
  getMaster(id: string): Observable<MasterPrompt> {
    return this.http
      .get<ApiDataResponse<MasterPrompt>>(`${this.baseUrl}/masters/${id}`)
      .pipe(map((response) => response.data));
  }

  /** Cria um novo prompt master. */
  createMaster(payload: MasterPromptPayload): Observable<MasterPrompt> {
    return this.http
      .post<ApiDataResponse<MasterPrompt>>(`${this.baseUrl}/masters`, payload)
      .pipe(map((response) => response.data));
  }

  /** Atualiza um prompt master existente. */
  updateMaster(id: string, payload: MasterPromptPayload): Observable<MasterPrompt> {
    return this.http
      .put<ApiDataResponse<MasterPrompt>>(`${this.baseUrl}/masters/${id}`, payload)
      .pipe(map((response) => response.data));
  }

  /** Remove permanentemente um prompt master pelo ID. */
  deleteMaster(id: string): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/masters/${id}`);
  }

  /** Alterna o status ativo/inativo de um prompt master. */
  toggleMaster(id: string): Observable<MasterPrompt> {
    return this.http
      .patch<ApiDataResponse<MasterPrompt>>(`${this.baseUrl}/masters/${id}/toggle`, {})
      .pipe(map((response) => response.data));
  }

  /**
   * Lista prompts de segmento com filtros opcionais.
   * @param filters Filtros: search, page, withTrashed (incluir registros excluídos)
   * @returns Observable com lista paginada de prompts de segmento
   */
  listSegments(filters?: {
    search?: string;
    page?: number;
    withTrashed?: boolean;
  }): Observable<Paginated<SegmentPrompt>> {
    const params = new URLSearchParams();
    if (filters?.search) params.set('search', filters.search);
    if (filters?.page) params.set('page', String(filters.page));
    if (filters?.withTrashed) params.set('with_trashed', '1');

    const query = params.toString();
    return this.http.get<Paginated<SegmentPrompt>>(
      `${this.baseUrl}/segments${query ? `?${query}` : ''}`,
    );
  }

  /** Retorna um prompt de segmento pelo ID. */
  getSegment(id: string): Observable<SegmentPrompt> {
    return this.http
      .get<ApiDataResponse<SegmentPrompt>>(`${this.baseUrl}/segments/${id}`)
      .pipe(map((response) => response.data));
  }

  /** Cria um novo prompt de segmento. */
  createSegment(payload: SegmentPromptPayload): Observable<SegmentPrompt> {
    return this.http
      .post<ApiDataResponse<SegmentPrompt>>(`${this.baseUrl}/segments`, payload)
      .pipe(map((response) => response.data));
  }

  /** Atualiza um prompt de segmento existente. */
  updateSegment(id: string, payload: SegmentPromptPayload): Observable<SegmentPrompt> {
    return this.http
      .put<ApiDataResponse<SegmentPrompt>>(`${this.baseUrl}/segments/${id}`, payload)
      .pipe(map((response) => response.data));
  }

  /** Remove (soft-delete) um prompt de segmento pelo ID. */
  deleteSegment(id: string): Observable<null> {
    return this.http.delete<null>(`${this.baseUrl}/segments/${id}`);
  }

  /** Restaura um prompt de segmento previamente excluído (soft-delete). */
  restoreSegment(id: string): Observable<SegmentPrompt> {
    return this.http
      .post<{ data: SegmentPrompt }>(`${this.baseUrl}/segments/${id}/restore`, {})
      .pipe(map((response) => response.data));
  }

  /**
   * Lista prompts vinculados a planos da plataforma.
   * @param filters Filtros opcionais: search, page
   * @returns Observable com lista paginada de prompts de plano
   */
  listPlans(filters?: { search?: string; page?: number }): Observable<Paginated<PlanPrompt>> {
    const params = new URLSearchParams();
    if (filters?.search) params.set('search', filters.search);
    if (filters?.page) params.set('page', String(filters.page));

    const query = params.toString();
    return this.http.get<Paginated<PlanPrompt>>(`${this.baseUrl}/plans${query ? `?${query}` : ''}`);
  }

  /** Retorna um prompt de plano pelo ID. */
  getPlan(id: string): Observable<PlanPrompt> {
    return this.http
      .get<ApiDataResponse<PlanPrompt>>(`${this.baseUrl}/plans/${id}`)
      .pipe(map((response) => response.data));
  }

  /** Atualiza o prompt associado a um plano da plataforma. */
  updatePlan(id: string, payload: PlanPromptPayload): Observable<PlanPrompt> {
    return this.http
      .put<ApiDataResponse<PlanPrompt>>(`${this.baseUrl}/plans/${id}`, payload)
      .pipe(map((response) => response.data));
  }

  /**
   * Lista prompts em quarentena aguardando aprovação ou rejeição.
   * @param filters Filtros opcionais: search, page
   * @returns Observable com lista paginada de prompts em quarentena
   */
  listQuarantine(filters?: {
    search?: string;
    page?: number;
  }): Observable<Paginated<QuarantinedPrompt>> {
    const params = new URLSearchParams();
    if (filters?.search) params.set('search', filters.search);
    if (filters?.page) params.set('page', String(filters.page));

    const query = params.toString();
    return this.http.get<Paginated<QuarantinedPrompt>>(
      `${this.baseUrl}/quarantine${query ? `?${query}` : ''}`,
    );
  }

  /** Aprova um prompt em quarentena, liberando-o para uso. */
  approvePrompt(id: string): Observable<null> {
    return this.http.post<null>(`${this.baseUrl}/quarantine/${id}/approve`, {});
  }

  /**
   * Rejeita um prompt em quarentena com justificativa obrigatória.
   * @param reason Motivo da rejeição exibido ao tenant
   */
  rejectPrompt(id: string, reason: string): Observable<null> {
    return this.http.post<null>(`${this.baseUrl}/quarantine/${id}/reject`, { reason });
  }

  /**
   * Vincula um segmento de prompt a um tenant específico.
   * @param tenantId ID do tenant alvo
   * @param segmentId ID do segmento a ser atribuído
   */
  assignSegment(tenantId: string, segmentId: string): Observable<null> {
    return this.http.put<null>(`${environment.apiUrl}/platform/tenants/${tenantId}/segment`, {
      segment_id: segmentId,
    });
  }

  /**
   * Retorna preview do prompt composto resolvido para o tenant autenticado.
   * Útil para depurar a composição master + segmento + plano + tenant.
   * @returns Observable com o prompt resolvido e seus componentes individuais
   */
  resolvePromptPreview(): Observable<PromptResolveResponse> {
    return this.http
      .get<ApiDataResponse<PromptResolveResponse>>(`${environment.apiUrl}/ai/prompt/resolve`)
      .pipe(map((response) => response.data));
  }
}
