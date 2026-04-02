import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable, map } from 'rxjs';
import { environment } from '@env/environment';
import { type Paginated } from '../../../shared/types/pagination';
import {
  type AiAgent,
  type AiAgentPayload,
  type AiAgentFile,
  type AiToolCatalogItem,
  type AiAgentToolLink,
  type AiAgentSkill,
  type AiAgentSkillPayload,
  type AiAgentTrigger,
  type AiAgentTriggerPayload,
  type AiAgentVoiceConfig,
  type AiAgentChannelConfig,
  type AutopilotRun,
} from '@ai/models/ai.model';

/**
 * Service para gestão de Agentes de IA v2.
 *
 * Responsável por operações CRUD de agentes, files, tools, skills,
 * channels, triggers e voice config.
 *
 * @class AiAgentService
 */
@Injectable({ providedIn: 'root' })
export class AiAgentService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/ai/agents`;
  private readonly toolsBaseUrl = `${environment.apiUrl}/ai/tools`;

  // ===== Agent CRUD =====

  /**
   * Lista todos os agentes com paginação.
   * @param params Parâmetros de paginação e filtros
   */
  list(
    params: { page?: number; per_page?: number; type?: string } = {},
  ): Observable<Paginated<AiAgent>> {
    let httpParams = new HttpParams();
    if (params.page) httpParams = httpParams.set('page', String(params.page));
    if (params.per_page) httpParams = httpParams.set('per_page', String(params.per_page));
    if (params.type) httpParams = httpParams.set('type', params.type);

    return this.http.get<Paginated<AiAgent>>(this.baseUrl, { params: httpParams });
  }

  /**
   * Busca um agente específico por ID.
   * @param id ID do agente
   */
  get(id: string): Observable<AiAgent> {
    return this.http.get<{ data: AiAgent }>(`${this.baseUrl}/${id}`).pipe(map((resp) => resp.data));
  }

  /**
   * Cria um novo agente.
   * @param payload Dados do agente
   */
  create(payload: AiAgentPayload): Observable<AiAgent> {
    return this.http.post<{ data: AiAgent }>(this.baseUrl, payload).pipe(map((resp) => resp.data));
  }

  /**
   * Atualiza um agente existente.
   * @param id ID do agente
   * @param payload Dados a atualizar
   */
  update(id: string, payload: Partial<AiAgentPayload>): Observable<AiAgent> {
    return this.http
      .put<{ data: AiAgent }>(`${this.baseUrl}/${id}`, payload)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Remove um agente.
   * @param id ID do agente
   */
  delete(id: string): Observable<void> {
    return this.http.delete<void>(`${this.baseUrl}/${id}`);
  }

  /**
   * Ativa ou desativa um agente.
   * @param id ID do agente
   * @param isActive Novo estado
   */
  toggleActive(id: string, isActive: boolean): Observable<AiAgent> {
    return this.update(id, { is_active: isActive });
  }

  /**
   * Executa uma simulação direta do agente (sem playbook).
   * @param agentId ID do agente
   * @param payload Mensagem para simulação
   */
  simulate(agentId: string, payload: { message: string }): Observable<AutopilotRun> {
    return this.http
      .post<{ data: AutopilotRun }>(`${this.baseUrl}/${agentId}/simulate`, payload)
      .pipe(map((resp) => resp.data));
  }

  // ===== Files =====

  /**
   * Lista core files de um agente.
   * @param agentId ID do agente
   */
  getFiles(agentId: string): Observable<AiAgentFile[]> {
    return this.http
      .get<{ data: AiAgentFile[] }>(`${this.baseUrl}/${agentId}/files`)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Busca um file específico de um agente.
   * @param agentId ID do agente
   * @param slug Slug do file (e.g. AGENTS.md)
   */
  getFile(agentId: string, slug: string): Observable<AiAgentFile> {
    return this.http
      .get<{ data: AiAgentFile }>(`${this.baseUrl}/${agentId}/files/${slug}`)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Atualiza conteúdo de um file do agente.
   * @param agentId ID do agente
   * @param slug Slug do file
   * @param content Conteúdo do file
   */
  updateFile(agentId: string, slug: string, content: string): Observable<AiAgentFile> {
    return this.http
      .put<{ data: AiAgentFile }>(`${this.baseUrl}/${agentId}/files/${slug}`, { content })
      .pipe(map((resp) => resp.data));
  }

  // ===== Tools =====

  /**
   * Lista catálogo completo de tools disponíveis.
   */
  getToolsCatalog(): Observable<AiToolCatalogItem[]> {
    return this.http
      .get<{ data: AiToolCatalogItem[] }>(`${this.toolsBaseUrl}/catalog`)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Lista os nomes das tools predefinidas para uma Role.
   */
  getToolsPreset(role: string): Observable<string[]> {
    return this.http
      .get<{ data: string[] }>(`${this.toolsBaseUrl}/presets/${role}`)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Lista tools vinculadas a um agente.
   * @param agentId ID do agente
   */
  getAgentTools(agentId: string): Observable<AiAgentToolLink[]> {
    return this.http
      .get<{ data: Record<string, unknown>[] }>(`${this.baseUrl}/${agentId}/tools`)
      .pipe(
        map((resp) =>
          resp.data
            .map((item) => this.normalizeToolLink(item))
            .filter((item): item is AiAgentToolLink => item !== null),
        ),
      );
  }

  /**
   * Atualiza tools vinculadas a um agente.
   * @param agentId ID do agente
   * @param toolNames Nomes das tools a vincular
   */
  updateAgentTools(agentId: string, toolNames: string[]): Observable<AiAgentToolLink[]> {
    return this.http
      .put<{
        data: Record<string, unknown>[];
      }>(`${this.baseUrl}/${agentId}/tools`, { tool_names: toolNames })
      .pipe(
        map((resp) =>
          resp.data
            .map((item) => this.normalizeToolLink(item))
            .filter((item): item is AiAgentToolLink => item !== null),
        ),
      );
  }

  // ===== Skills =====

  /**
   * Lista skills de um agente.
   * @param agentId ID do agente
   */
  getSkills(agentId: string): Observable<AiAgentSkill[]> {
    return this.http
      .get<{ data: AiAgentSkill[] }>(`${this.baseUrl}/${agentId}/skills`)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Cria uma skill para um agente.
   * @param agentId ID do agente
   * @param payload Dados da skill
   */
  createSkill(agentId: string, payload: AiAgentSkillPayload): Observable<AiAgentSkill> {
    return this.http
      .post<{ data: AiAgentSkill }>(`${this.baseUrl}/${agentId}/skills`, payload)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Atualiza uma skill do agente.
   * @param agentId ID do agente
   * @param skillId ID da skill
   * @param payload Dados a atualizar
   */
  updateSkill(
    agentId: string,
    skillId: string,
    payload: Partial<AiAgentSkillPayload>,
  ): Observable<AiAgentSkill> {
    return this.http
      .put<{ data: AiAgentSkill }>(`${this.baseUrl}/${agentId}/skills/${skillId}`, payload)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Remove uma skill do agente.
   * @param agentId ID do agente
   * @param skillId ID da skill
   */
  deleteSkill(agentId: string, skillId: string): Observable<void> {
    return this.http.delete<void>(`${this.baseUrl}/${agentId}/skills/${skillId}`);
  }

  // ===== Channels =====

  /**
   * Lista canais configurados de um agente.
   * @param agentId ID do agente
   */
  getChannels(agentId: string): Observable<AiAgentChannelConfig[]> {
    return this.http
      .get<{ data: AiAgentChannelConfig[] }>(`${this.baseUrl}/${agentId}/channels`)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Atualiza canais de um agente.
   * @param agentId ID do agente
   * @param channels Array de canais habilitados
   */
  updateChannels(agentId: string, channels: string[]): Observable<AiAgentChannelConfig[]> {
    return this.http
      .put<{ data: AiAgentChannelConfig[] }>(`${this.baseUrl}/${agentId}/channels`, { channels })
      .pipe(map((resp) => resp.data));
  }

  // ===== Triggers =====

  /**
   * Lista triggers de um agente.
   * @param agentId ID do agente
   */
  getTriggers(agentId: string): Observable<AiAgentTrigger[]> {
    return this.http
      .get<{ data: AiAgentTrigger[] }>(`${this.baseUrl}/${agentId}/triggers`)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Cria um trigger para um agente.
   * @param agentId ID do agente
   * @param payload Dados do trigger
   */
  createTrigger(agentId: string, payload: AiAgentTriggerPayload): Observable<AiAgentTrigger> {
    return this.http
      .post<{ data: AiAgentTrigger }>(`${this.baseUrl}/${agentId}/triggers`, payload)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Atualiza um trigger do agente.
   * @param agentId ID do agente
   * @param triggerId ID do trigger
   * @param payload Dados a atualizar
   */
  updateTrigger(
    agentId: string,
    triggerId: string,
    payload: Partial<AiAgentTriggerPayload>,
  ): Observable<AiAgentTrigger> {
    return this.http
      .put<{ data: AiAgentTrigger }>(`${this.baseUrl}/${agentId}/triggers/${triggerId}`, payload)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Remove um trigger do agente.
   * @param agentId ID do agente
   * @param triggerId ID do trigger
   */
  deleteTrigger(agentId: string, triggerId: string): Observable<void> {
    return this.http.delete<void>(`${this.baseUrl}/${agentId}/triggers/${triggerId}`);
  }

  // ===== Voice =====

  /**
   * Busca configuração de voz de um agente.
   * @param agentId ID do agente
   */
  getVoiceConfig(agentId: string): Observable<AiAgentVoiceConfig> {
    return this.http.get<{ data: AiAgentVoiceConfig }>(`${this.baseUrl}/${agentId}/voice`).pipe(
      map((resp) => ({
        ...resp.data,
        voice_response_mode: this.normalizeVoiceResponseMode(resp.data.voice_response_mode),
      })),
    );
  }

  /**
   * Atualiza configuração de voz de um agente.
   * @param agentId ID do agente
   * @param config Configuração de voz
   */
  updateVoiceConfig(
    agentId: string,
    config: Partial<AiAgentVoiceConfig>,
  ): Observable<AiAgentVoiceConfig> {
    const payload: Partial<AiAgentVoiceConfig> = {
      ...config,
    };

    if (config.voice_response_mode !== undefined) {
      payload.voice_response_mode = this.normalizeVoiceResponseMode(config.voice_response_mode);
    }

    return this.http
      .put<{ data: AiAgentVoiceConfig }>(`${this.baseUrl}/${agentId}/voice`, payload)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Normalizes a raw tool link object from the API into a structured AiAgentToolLink.
   *
   * @param item - Raw tool object from API
   * @returns Normalized tool link or null if required fields are missing
   */
  private normalizeToolLink(item: Record<string, unknown>): AiAgentToolLink | null {
    const toolName = this.readString(item['tool_name']) ?? this.readString(item['name']);
    const toolId = this.readString(item['tool_id']) ?? toolName;

    if (!toolName || !toolId) {
      return null;
    }

    return {
      tool_id: toolId,
      tool_name: toolName,
    };
  }

  /**
   * Normalizes legacy voice response mode values to the current enum values.
   *
   * @param mode - Raw mode value from API
   * @returns Normalized voice response mode
   */
  private normalizeVoiceResponseMode(mode: unknown): AiAgentVoiceConfig['voice_response_mode'] {
    if (mode === 'text_only') return 'text';
    if (mode === 'audio_only') return 'audio';
    if (mode === 'both') return 'mixed';
    if (mode === 'audio' || mode === 'mixed') return mode;
    return 'text';
  }

  /**
   * Safely extracts a non-empty trimmed string from an unknown value.
   *
   * @param value - Value to extract from
   * @returns String or null if not a valid non-empty string
   */
  private readString(value: unknown): string | null {
    if (typeof value !== 'string') {
      return null;
    }

    const normalized = value.trim();

    return normalized.length > 0 ? normalized : null;
  }
}
