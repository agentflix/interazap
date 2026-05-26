import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable, map } from 'rxjs';
import { environment } from '@env/environment';
import { type Paginated } from '../../../shared/types/pagination';
import {
  type AiKnowledge,
  type KnowledgeChunk,
  type AiKnowledgePayload,
  type KnowledgeSearchResult,
  type KnowledgeStats,
  type KnowledgeSearchMode,
} from '@ai/models/ai.model';

interface AiKnowledgeApiItem {
  id: string;
  name: string;
  original_filename: string;
  file_type: 'txt' | 'csv' | 'markdown' | 'json' | 'pdf' | 'url';
  file_size_bytes: number;
  file_size_formatted: string;
  version: number;
  chunk_count: number;
  embedding_status: 'pending' | 'processing' | 'ready' | 'failed';
  error_message?: string | null;
  is_ready?: boolean;
  is_processing?: boolean;
  is_failed?: boolean;
  can_reprocess?: boolean;
  metadata?: Record<string, unknown> | null;
  created_at?: string;
  updated_at?: string;
}

interface ApiDataResponse<T> {
  data: T;
}

interface ApiCollectionWithMeta<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

interface BulkCountResponse {
  deleted_count?: number;
  queued_count?: number;
}

/**
 * Serviço para gestão da Base de Conhecimento da IA.
 *
 * Responsável por upload, indexação e consulta de documentos
 * que alimentam o contexto dos agentes de IA.
 */
@Injectable({ providedIn: 'root' })
export class AiKnowledgeService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/ai/knowledge`;

  /**
   * Lista entradas da base de conhecimento com paginação.
   * @param params Parâmetros de paginação e filtros
   */
  list(
    params: {
      page?: number;
      per_page?: number;
      status?: string;
      content_type?: string;
      search?: string;
    } = {},
  ): Observable<Paginated<AiKnowledge>> {
    let httpParams = new HttpParams();
    if (params.page) httpParams = httpParams.set('page', String(params.page));
    if (params.per_page) httpParams = httpParams.set('per_page', String(params.per_page));
    if (params.status) httpParams = httpParams.set('status', params.status);
    if (params.content_type) httpParams = httpParams.set('content_type', params.content_type);
    if (params.search) httpParams = httpParams.set('search', params.search);

    return this.http.get<Paginated<AiKnowledgeApiItem>>(this.baseUrl, { params: httpParams }).pipe(
      map((response) => ({
        ...response,
        data: (response.data ?? []).map((item) => this.mapKnowledge(item)),
      })),
    );
  }

  /**
   * Busca uma entrada específica por ID.
   * @param id ID da entrada
   */
  get(id: string): Observable<AiKnowledge> {
    return this.http
      .get<ApiDataResponse<AiKnowledgeApiItem>>(`${this.baseUrl}/${id}`)
      .pipe(map((resp) => this.mapKnowledge(resp.data)));
  }

  /**
   * Cria uma nova entrada de conhecimento (texto).
   * @param payload Dados da entrada
   */
  create(payload: AiKnowledgePayload): Observable<AiKnowledge> {
    return this.http
      .post<ApiDataResponse<AiKnowledgeApiItem>>(this.baseUrl, payload)
      .pipe(map((resp) => this.mapKnowledge(resp.data)));
  }

  /**
   * Upload de arquivo (PDF, CSV) para a base de conhecimento.
   * @param file Arquivo a ser enviado
   * @param title Título da entrada
   */
  upload(file: File, title: string): Observable<AiKnowledge> {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('name', title);

    return this.http
      .post<ApiDataResponse<AiKnowledgeApiItem>>(`${this.baseUrl}/upload`, formData)
      .pipe(map((resp) => this.mapKnowledge(resp.data)));
  }

  /**
   * Ingere uma URL pública na base de conhecimento.
   * @param url URL pública a ser ingerida
   * @param title Título da entrada
   */
  ingestUrl(url: string, title: string): Observable<AiKnowledge> {
    return this.http
      .post<ApiDataResponse<AiKnowledgeApiItem>>(`${this.baseUrl}/url`, { url, title })
      .pipe(map((resp) => this.mapKnowledge(resp.data)));
  }

  /**
   * Remove uma entrada da base de conhecimento.
   * @param id ID da entrada
   */
  delete(id: string): Observable<void> {
    return this.http.delete<void>(`${this.baseUrl}/${id}`);
  }

  /**
   * Reprocessa uma entrada para re-indexação.
   * @param id ID da entrada
   */
  reindex(id: string): Observable<AiKnowledge> {
    return this.http
      .post<ApiDataResponse<AiKnowledgeApiItem>>(`${this.baseUrl}/${id}/reindex`, {})
      .pipe(map((resp) => this.mapKnowledge(resp.data)));
  }

  /**
   * Remove múltiplos documentos em lote pelos IDs.
   * @param ids Lista de IDs dos documentos a excluir
   */
  bulkDelete(ids: string[]): Observable<BulkCountResponse> {
    return this.http.delete<BulkCountResponse>(`${this.baseUrl}/bulk`, {
      body: { ids },
    });
  }

  /**
   * Reindexação em lote de documentos pelos IDs.
   * @param ids Lista de IDs dos documentos a reindexar
   */
  bulkReindex(ids: string[]): Observable<BulkCountResponse> {
    return this.http.post<BulkCountResponse>(`${this.baseUrl}/bulk-reindex`, { ids });
  }

  /**
   * Carrega estatísticas da base de conhecimento.
   */
  getStats(): Observable<KnowledgeStats> {
    return this.http
      .get<ApiDataResponse<KnowledgeStats>>(`${this.baseUrl}/stats`)
      .pipe(map((response) => response.data));
  }

  /**
   * Busca semântica na base de conhecimento.
   * @param query Termo de busca
   * @param limit Máximo de resultados
   */
  search(
    query: string,
    limit = 10,
    minScore = 0.3,
    mode: KnowledgeSearchMode = 'vector',
  ): Observable<KnowledgeSearchResult[]> {
    return this.http
      .post<ApiDataResponse<KnowledgeSearchResult[]>>(`${this.baseUrl}/search`, {
        query,
        limit,
        min_score: minScore,
        mode,
      })
      .pipe(map((resp) => resp.data));
  }

  /**
   * Lista chunks de um documento específico.
   */
  getChunks(
    documentId: string,
    page = 1,
    perPage = 20,
  ): Observable<ApiCollectionWithMeta<KnowledgeChunk>> {
    const params = new HttpParams().set('page', String(page)).set('per_page', String(perPage));

    return this.http.get<ApiCollectionWithMeta<KnowledgeChunk>>(
      `${this.baseUrl}/${documentId}/chunks`,
      { params },
    );
  }

  /**
   * Converte um item da API para o modelo da aplicação, aplicando
   * aliases de compatibilidade para nomes de campos e valores de status.
   * @param item Item bruto da resposta da API
   * @returns Objeto AiKnowledge normalizado
   */
  private mapKnowledge(item: AiKnowledgeApiItem): AiKnowledge {
    const contentTypeMap: Record<AiKnowledgeApiItem['file_type'], AiKnowledge['content_type']> = {
      txt: 'text',
      markdown: 'text',
      csv: 'csv',
      json: 'text',
      pdf: 'pdf',
      url: 'url',
    };

    const statusMap: Record<AiKnowledgeApiItem['embedding_status'], AiKnowledge['status']> = {
      pending: 'pending',
      processing: 'processing',
      ready: 'indexed',
      failed: 'failed',
    };

    return {
      ...item,
      title: item.name,
      content_type: contentTypeMap[item.file_type],
      status: statusMap[item.embedding_status],
      token_count: 0,
      file_path: null,
      source_url: null,
    };
  }
}
