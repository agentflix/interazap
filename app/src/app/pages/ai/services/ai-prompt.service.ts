import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable, map } from 'rxjs';
import { environment } from '@env/environment';
import { type Paginated } from '../../../shared/types/pagination';
import { type AiPrompt, type AiPromptPayload } from '@ai/models/ai.model';

interface TenantPromptApiResponse {
  data: {
    id: string;
    tenant_id: string;
    content: string;
    segment?: { name?: string } | null;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
  } | null;
}

/**
 * Service para gestão de Templates de Prompt.
 *
 * Responsável por CRUD de prompts configuráveis por agente,
 * com suporte a variáveis de interpolação.
 *
 * @class AiPromptService
 */
@Injectable({ providedIn: 'root' })
export class AiPromptService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/ai/prompt`;

  /**
   * Lista prompts com paginação e filtros.
   * @param params Parâmetros de paginação e filtros
   */
  list(
    params: { page?: number; per_page?: number; agent_id?: string; category?: string } = {},
  ): Observable<Paginated<AiPrompt>> {
    let httpParams = new HttpParams();
    if (params.page) httpParams = httpParams.set('page', String(params.page));
    if (params.per_page) httpParams = httpParams.set('per_page', String(params.per_page));
    if (params.agent_id) httpParams = httpParams.set('agent_id', params.agent_id);
    if (params.category) httpParams = httpParams.set('category', params.category);

    return this.http.get<TenantPromptApiResponse>(this.baseUrl, { params: httpParams }).pipe(
      map((response) => {
        const prompt = this.mapTenantPromptToAiPrompt(response.data);
        const data = prompt ? [prompt] : [];

        return {
          data,
          meta: {
            total: data.length,
            per_page: params.per_page ?? 10,
            current_page: 1,
            last_page: 1,
          },
        };
      }),
    );
  }

  /**
   * Busca um prompt específico por ID.
   * @param id ID do prompt
   */
  get(id: string): Observable<AiPrompt> {
    return this.http.get<TenantPromptApiResponse>(this.baseUrl).pipe(
      map((response) => {
        const prompt = this.mapTenantPromptToAiPrompt(response.data);
        if (!prompt || prompt.id !== id) {
          throw new Error('Prompt not found');
        }
        return prompt;
      }),
    );
  }

  /**
   * Cria um novo prompt.
   * @param payload Dados do prompt
   */
  create(payload: AiPromptPayload): Observable<AiPrompt> {
    return this.http
      .put<TenantPromptApiResponse>(this.baseUrl, { content: payload.template })
      .pipe(map((response) => this.requirePrompt(response.data)));
  }

  /**
   * Atualiza um prompt existente.
   * @param id ID do prompt
   * @param payload Dados a atualizar
   */
  update(id: string, payload: Partial<AiPromptPayload>): Observable<AiPrompt> {
    if (!payload.template) {
      throw new Error('Template is required to update prompt');
    }

    return this.http
      .put<TenantPromptApiResponse>(this.baseUrl, { content: payload.template })
      .pipe(map((response) => this.requirePrompt(response.data, id)));
  }

  /**
   * Remove um prompt.
   * @param id ID do prompt
   */
  delete(id: string): Observable<void> {
    void id;
    return this.http.delete<void>(this.baseUrl);
  }

  /**
   * Restaura o prompt para a versão anterior.
   */
  rollback(): Observable<void> {
    return this.http.post<void>(`${this.baseUrl}/rollback`, {});
  }

  /**
   * Testa um prompt com variáveis de exemplo.
   * @param id ID do prompt
   * @param variables Variáveis para interpolação
   */
  test(id: string, variables: Record<string, string>): Observable<{ rendered: string }> {
    return this.get(id).pipe(
      map((prompt) => {
        let rendered = prompt.template;
        Object.entries(variables).forEach(([key, value]) => {
          rendered = rendered.replaceAll(`{{${key}}}`, value).replaceAll(`{${key}}`, value);
        });

        return { rendered };
      }),
    );
  }

  /**
   * Extrai variáveis de um template de prompt.
   * @param template Template do prompt
   */
  extractVariables(template: string): string[] {
    const matches = template.match(/\{\{(\w+)\}\}|\{(\w+)\}/g) ?? [];

    return [...new Set(matches.map((match) => match.replace(/^\{\{?/, '').replace(/\}\}?$/, '')))];
  }

  /**
   * Maps a tenant prompt API response to the application AiPrompt model.
   *
   * @param data - Raw tenant prompt from API
   * @returns Normalized AiPrompt or null if data is empty
   */
  private mapTenantPromptToAiPrompt(data: TenantPromptApiResponse['data']): AiPrompt | null {
    if (!data) {
      return null;
    }

    return {
      id: data.id,
      agent_id: data.tenant_id,
      name: 'Prompt do Inquilino',
      template: data.content,
      variables: this.extractVariables(data.content),
      category: data.segment?.name ?? null,
      is_active: data.is_active,
      created_at: data.created_at,
      updated_at: data.updated_at,
    };
  }

  /**
   * Validates that a prompt exists and optionally matches the expected ID.
   *
   * @param data - Raw tenant prompt from API
   * @param expectedId - Optional ID to validate against
   * @returns Normalized AiPrompt
   * @throws Error if prompt is null or ID mismatch
   */
  private requirePrompt(data: TenantPromptApiResponse['data'], expectedId?: string): AiPrompt {
    const prompt = this.mapTenantPromptToAiPrompt(data);

    if (!prompt || (expectedId && prompt.id !== expectedId)) {
      throw new Error('Prompt not found');
    }

    return prompt;
  }
}
