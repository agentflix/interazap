import { Injectable, Logger } from '@nestjs/common';
import axios, { AxiosInstance, AxiosRequestConfig } from 'axios';
import { ConfigService } from '@nestjs/config';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import type { AiToolDefinition } from '../models/tool.model';
import type {
  InternalApiEnvelope,
  InternalAiDelegateRunPayload,
} from '../models/internal-ai-client.model';
import { parsePositiveTimeout } from '../../../shared/utils/timeout.util';

/**
 * Serviço responsável por consumir a API interna do backend para prompts, contexto, tools e delegações.
 */
@Injectable()
export class InternalAiClientService {
  private readonly logger = new Logger(InternalAiClientService.name);
  private readonly http: AxiosInstance;

  private static readonly TOOLS_CACHE_TTL_SECONDS = 3600;

  constructor(
    private readonly configService: ConfigService,
    private readonly redisService: RedisService,
  ) {
    const baseUrl =
      this.configService.get<string>('api.url') ??
      this.configService.get<string>('INTERNAL_API_BASE_URL') ??
      'http://localhost:8000';
    const timeout = parsePositiveTimeout(
      this.configService.get<string | number>('INTERNAL_API_TIMEOUT'),
      5000,
    );

    this.http = axios.create({
      baseURL: `${baseUrl.replace(/\/+$/, '')}/api`,
      timeout,
      headers: {
        'X-Internal-Api-Key':
          this.configService.get<string>('internal.apiKey') ?? '',
      },
    });
  }

  /**
   * Busca o prompt base de AI para um tenant específico.
   * @param tenantId - Identificador do tenant dono do prompt.
   * @param traceId - Identificador opcional de rastreamento distribuído.
   * @returns Prompt base retornado pela API interna.
   */
  async fetchPrompt(tenantId: string, traceId?: string): Promise<string> {
    const response = await this.http.get<
      InternalApiEnvelope<{ prompt?: string }>
    >(
      `/internal/ai/prompt/${tenantId}`,
      this.buildRequestConfig(traceId, tenantId),
    );

    return response.data?.data?.prompt ?? '';
  }

  /**
   * Busca o contexto operacional associado a um ticket.
   * @param ticketId - Identificador do ticket consultado.
   * @param traceId - Identificador opcional de rastreamento distribuído.
   * @returns Contexto estruturado retornado pela API interna.
   */
  async fetchContext(
    ticketId: string,
    traceId?: string,
  ): Promise<Record<string, unknown>> {
    const response = await this.http.get<
      InternalApiEnvelope<Record<string, unknown>>
    >(`/internal/ai/context/${ticketId}`, this.buildRequestConfig(traceId));

    return response.data?.data ?? {};
  }

  /**
   * Busca a lista de tools disponíveis para um agente.
   * @param agentId - Identificador do agente consultado.
   * @param traceId - Identificador opcional de rastreamento distribuído.
   * @returns Lista de tools disponibilizadas para o agente.
   */
  async fetchTools(
    agentId: string,
    traceId?: string,
  ): Promise<AiToolDefinition[]> {
    const response = await this.http.get<
      InternalApiEnvelope<{ tools?: AiToolDefinition[] }>
    >(`/internal/ai/tools/${agentId}`, this.buildRequestConfig(traceId));

    return response.data?.data?.tools ?? [];
  }

  /**
   * Busca a lista de tools usando cache Redis para reduzir chamadas ao backend.
   * @param agentId - Identificador do agente consultado.
   * @param traceId - Identificador opcional de rastreamento distribuído.
   * @returns Lista de tools em cache ou recém-carregadas da API interna.
   */
  async fetchToolsCached(
    agentId: string,
    traceId?: string,
  ): Promise<AiToolDefinition[]> {
    const cacheKey = `autopilot:tools:${agentId}`;
    const cached = await this.redisService.get(cacheKey);

    if (cached) {
      try {
        return JSON.parse(cached) as AiToolDefinition[];
      } catch {
        // fall through to re-fetch on parse error
      }
    }

    const tools = await this.fetchTools(agentId, traceId);
    await this.redisService.set(
      cacheKey,
      JSON.stringify(tools),
      InternalAiClientService.TOOLS_CACHE_TTL_SECONDS,
    );

    return tools;
  }

  /**
   * Executa uma tool remota via endpoint HTTP interno.
   * @param toolName - Nome da tool a ser executada.
   * @param parameters - Argumentos enviados para a tool.
   * @param context - Contexto operacional serializado da run atual.
   * @param traceId - Identificador opcional de rastreamento distribuído.
   * @returns Resultado retornado pelo backend para a tool executada.
   */
  async executeRemoteTool(
    toolName: string,
    parameters: Record<string, unknown>,
    context: Record<string, unknown>,
    traceId?: string,
  ): Promise<Record<string, unknown>> {
    const normalizedContext: Record<string, unknown> = {
      ...context,
    };

    if (
      typeof normalizedContext['current_run_id'] !== 'string' &&
      typeof normalizedContext['run_id'] === 'string'
    ) {
      normalizedContext['current_run_id'] = normalizedContext['run_id'];
    }

    const response = await this.http.post<
      InternalApiEnvelope<Record<string, unknown>>
    >(
      `/internal/ai/tool/${toolName}`,
      {
        parameters,
        context: normalizedContext,
      },
      this.buildRequestConfig(traceId),
    );

    return response.data?.data ?? {};
  }

  /**
   * Solicita a criação de uma run filha para delegação de execução.
   * @param payload - Payload normalizado com dados da delegação.
   * @param traceId - Identificador opcional de rastreamento distribuído.
   * @returns Dados retornados pela API interna sobre a delegação criada.
   */
  async delegateRun(
    payload: InternalAiDelegateRunPayload,
    traceId?: string,
  ): Promise<Record<string, unknown>> {
    const response = await this.http.post<
      InternalApiEnvelope<Record<string, unknown>>
    >('/internal/ai/runs/delegate', payload, this.buildRequestConfig(traceId));

    return response.data?.data ?? {};
  }

  /**
   * Consulta o status atual de uma run de AI já existente.
   * @param runId - Identificador da run consultada.
   * @param tenantId - Identificador opcional do tenant para escopo da busca.
   * @param traceId - Identificador opcional de rastreamento distribuído.
   * @returns Estado atual retornado pela API interna para a run informada.
   */
  async fetchRunStatus(
    runId: string,
    tenantId?: string,
    traceId?: string,
  ): Promise<Record<string, unknown>> {
    const requestConfig: AxiosRequestConfig = {
      ...this.buildRequestConfig(traceId),
      ...(tenantId ? { params: { tenant_id: tenantId } } : {}),
    };

    const response = await this.http.get<
      InternalApiEnvelope<Record<string, unknown>>
    >(`/internal/ai/runs/${runId}`, requestConfig);

    return response.data?.data ?? {};
  }

  /**
   * Executa uma busca de conhecimento contextual no backend.
   * @param tenantId - Identificador do tenant que realizará a busca.
   * @param query - Texto usado como consulta de busca.
   * @param limit - Quantidade máxima de resultados esperados.
   * @param traceId - Identificador opcional de rastreamento distribuído.
   * @returns Lista de resultados retornados pela busca ou array vazio em caso de falha.
   */
  async searchKnowledge(
    tenantId: string,
    query: string,
    limit: number,
    traceId?: string,
  ): Promise<Array<Record<string, unknown>>> {
    try {
      const requestConfig: AxiosRequestConfig = {
        ...this.buildRequestConfig(traceId, tenantId),
        params: {
          tenant_id: tenantId,
          query,
          k: limit,
        },
      };

      const response = await this.http.get<
        InternalApiEnvelope<{ results?: Array<Record<string, unknown>> }>
      >('/internal/ai/knowledge/search', requestConfig);

      return response.data?.data?.results ?? [];
    } catch (error) {
      this.logger.warn(`Knowledge search failed: ${(error as Error).message}`);
      return [];
    }
  }

  /**
   * Constrói a configuração base da requisição Axios com cabeçalhos de rastreamento e tenant.
   * @param traceId - Identificador opcional de rastreamento distribuído.
   * @param tenantId - Identificador opcional do tenant para propagação do escopo.
   * @returns Configuração Axios com cabeçalhos quando fornecidos.
   */
  private buildRequestConfig(
    traceId?: string,
    tenantId?: string,
  ): AxiosRequestConfig {
    const headers: Record<string, string> = {};

    if (traceId && traceId.trim() !== '') {
      headers['X-Trace-Id'] = traceId;
      headers['X-Trace-ID'] = traceId;
    }

    if (tenantId && tenantId.trim() !== '') {
      headers['X-Tenant-Id'] = tenantId;
    }

    if (Object.keys(headers).length === 0) {
      return {};
    }

    return { headers };
  }
}
