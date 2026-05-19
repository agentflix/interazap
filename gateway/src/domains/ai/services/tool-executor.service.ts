import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { randomUUID } from 'crypto';
import { InternalAiClientService } from './internal-ai-client.service';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { AiMetricsService } from './ai-metrics.service';
import { GatewayConfigService } from '../../../shared/services/gateway-config.service';
import { tenantRoom, runRoom } from '../../../shared/constants';
import {
  getBooleanField,
  getNumberField,
  getStringField,
  toRecord,
} from '../../../shared/utils/payload-reader.util';
import { ToolStrategyRegistry } from './tool-strategies/tool-strategy.registry';
import { ToolStrategyRuntime } from './tool-strategies/tool-strategy.types';
import { ToolExecutionContext } from '../interfaces/tool-execution-context.interface';
import type { RedisBlockingListClient } from '../models/tool-executor.model';

export type { ToolExecutionContext } from '../interfaces/tool-execution-context.interface';

/** Hard cap on delegation depth to prevent runaway chains regardless of caller args. */
const MAX_DELEGATION_DEPTH = 3;

/**
 * Serviço responsável por executar tools do domínio AI com fallback entre Redis RPC e API interna.
 */
@Injectable()
export class ToolExecutorService {
  private static readonly TOOL_TIMEOUT_MS = 15_000;

  private readonly logger = new Logger(ToolExecutorService.name);
  private readonly delegationWaitTimeoutMs: number;
  private readonly toolRpcTimeoutMs: number;
  private readonly strategyRegistry: ToolStrategyRegistry;
  private readonly strategyRuntime: ToolStrategyRuntime;

  constructor(
    private readonly aiMetrics: AiMetricsService,
    private readonly internalAiClient: InternalAiClientService,
    private readonly redisService: RedisService,
    private readonly configService: ConfigService,
    private readonly gatewayConfigService: GatewayConfigService,
    strategyRegistry: ToolStrategyRegistry,
  ) {
    this.delegationWaitTimeoutMs = this.resolveTimeout(
      this.configService.get<number>('ai.delegationWaitTimeoutMs'),
      8000,
    );
    this.toolRpcTimeoutMs = this.resolveTimeout(
      this.configService.get<number>('ai.toolRpcTimeoutMs'),
      2000,
    );

    this.strategyRegistry = strategyRegistry;

    this.strategyRuntime = {
      normalizeSendMessageArgs: (args, context) =>
        this.normalizeSendMessageArgs(args, context),
      executeToolRpcWithHttpFallback: (name, args, context) =>
        this.executeToolRpcWithHttpFallback(name, args, context),
      executeDelegateToAgent: (args, context) =>
        this.executeDelegateToAgent(args, context),
    };
  }

  /**
   * Executa uma tool registrada e converte falhas em payloads padronizados de erro.
   * @param name - Nome da tool a ser executada.
   * @param args - Argumentos normalizados enviados para a tool.
   * @param context - Contexto operacional da run atual.
   * @returns Resultado serializável da execução da tool.
   */
  async executeTool(
    name: string,
    args: Record<string, unknown>,
    context: ToolExecutionContext,
  ): Promise<Record<string, unknown>> {
    try {
      const result = await Promise.race([
        this.executeToolInternal(name, args, context),
        new Promise<never>((_, reject) =>
          setTimeout(
            () =>
              reject(
                new Error(
                  `Tool '${name}' timed out after ${ToolExecutorService.TOOL_TIMEOUT_MS}ms`,
                ),
              ),
            ToolExecutorService.TOOL_TIMEOUT_MS,
          ),
        ),
      ]);
      const isSuccess = result['success'] === true;
      this.aiMetrics.recordToolCall(
        context.agentId,
        name,
        isSuccess ? 'success' : 'error',
      );
      return result;
    } catch (error) {
      this.aiMetrics.recordToolCall(context.agentId, name, 'error');
      return {
        success: false,
        error: (error as Error).message,
      };
    }
  }

  /**
   * Resolve a estratégia da tool e executa sem capturar erros.
   * @param name - Nome da tool a ser executada.
   * @param args - Argumentos normalizados enviados para a tool.
   * @param context - Contexto operacional da run atual.
   * @returns Resultado da execução da estratégia selecionada.
   */
  private async executeToolInternal(
    name: string,
    args: Record<string, unknown>,
    context: ToolExecutionContext,
  ): Promise<Record<string, unknown>> {
    const strategy = this.strategyRegistry.resolve(name);
    return strategy.execute(name, args, context, this.strategyRuntime);
  }

  /**
   * Executa a delegação de execução para outro agente com verificações de profundidade e ciclo.
   * @param args - Argumentos da tool contendo `target_agent_id` e opções de delegação.
   * @param context - Contexto operacional da run atual.
   * @returns Resultado da delegação ou payload de erro estruturado.
   */
  private async executeDelegateToAgent(
    args: Record<string, unknown>,
    context: ToolExecutionContext,
  ): Promise<Record<string, unknown>> {
    const targetAgentId =
      this.readString(args, 'target_agent_id') ?? context.targetAgentId ?? '';
    if (targetAgentId === '') {
      return {
        success: false,
        error: 'target_agent_id is required',
      };
    }

    const returnAfter = this.readBoolean(args, 'return_after', true);
    const maxDepth = Math.min(
      this.readNumber(args, 'max_depth', MAX_DELEGATION_DEPTH),
      MAX_DELEGATION_DEPTH,
    );
    const currentDepth =
      context.delegationDepth ?? this.readNumber(args, 'delegation_depth', 0);

    if (currentDepth >= maxDepth) {
      return {
        success: false,
        error: 'Delegation max depth reached in gateway pre-check.',
        delegated: false,
        max_depth: maxDepth,
        delegation_depth: currentDepth,
      };
    }

    const delegationStack = this.resolveDelegationStack(args, context);
    if (delegationStack.includes(targetAgentId)) {
      return {
        success: false,
        error: 'Circular delegation detected in gateway stack pre-check.',
        delegated: false,
        target_agent_id: targetAgentId,
      };
    }

    await this.publishDelegationEvent('ai.run.delegating', context, {
      target_agent_id: targetAgentId,
      source_agent_id: context.agentId,
      delegation_depth: currentDepth,
      return_after: returnAfter,
    });

    const delegateResult = await this.internalAiClient.delegateRun(
      {
        tenant_id: context.tenantId,
        parent_run_id: context.runId,
        target_agent_id: targetAgentId,
        delegation_stack: delegationStack,
        input_context: this.resolveDelegationInputContext(args, context),
      },
      context.traceId,
    );

    const childRunId = this.readString(delegateResult, 'child_run_id') ?? '';

    if (childRunId === '') {
      this.aiMetrics.recordDelegation(context.agentId, targetAgentId, 'error');
      return {
        success: false,
        delegated: false,
        error: 'Delegation failed.',
      };
    }

    await this.publishDelegationEvent('ai.run.delegated', context, {
      target_agent_id: targetAgentId,
      source_agent_id: context.agentId,
      child_run_id: childRunId || null,
      return_after: returnAfter,
    });

    this.aiMetrics.recordDelegation(context.agentId, targetAgentId, 'success');

    if (!returnAfter) {
      return {
        success: true,
        delegated: true,
        handoff: 'child_takes_over',
        return_after: false,
        child_run_id: childRunId || null,
        target_agent_id: targetAgentId,
        message: 'Delegation completed; child run takes over execution.',
      };
    }

    const childRun = await this.awaitDelegationResult(context.runId);

    return {
      success: true,
      delegated: true,
      return_after: true,
      child_run_id: childRunId,
      child_status: this.readString(childRun, 'status') ?? 'unknown',
      child_output: childRun['output'] ?? null,
      target_agent_id: targetAgentId,
      message: 'Delegation completed and child run result returned to parent.',
    };
  }

  /**
   * Normaliza os argumentos da tool `send_message` garantindo campos obrigatórios.
   * @param args - Argumentos originais da tool possivelmente com campos alternativos.
   * @param context - Contexto operacional da run atual usado para preencher `ticket_id`.
   * @returns Argumentos normalizados com `content` e `ticket_id` resolvidos.
   */
  private normalizeSendMessageArgs(
    args: Record<string, unknown>,
    context: ToolExecutionContext,
  ): Record<string, unknown> {
    const normalizedArgs = { ...args };

    const content = this.readString(normalizedArgs, 'content');
    if (content === null) {
      const fallbackContent =
        this.readString(normalizedArgs, 'text') ??
        this.readString(normalizedArgs, 'message') ??
        this.readString(normalizedArgs, 'body');

      if (fallbackContent !== null) {
        normalizedArgs['content'] = fallbackContent;
      }
    }

    const ticketId = this.readString(normalizedArgs, 'ticket_id');
    if (ticketId === null && context.ticketId) {
      normalizedArgs['ticket_id'] = context.ticketId;
    }

    return normalizedArgs;
  }

  /**
   * Tenta executar a tool via Redis RPC com fallback para HTTP quando o RPC falhar.
   * @param name - Nome da tool a ser executada.
   * @param args - Argumentos normalizados da tool.
   * @param context - Contexto operacional da run atual.
   * @returns Resultado da execução via RPC ou HTTP.
   */
  private async executeToolRpcWithHttpFallback(
    name: string,
    args: Record<string, unknown>,
    context: ToolExecutionContext,
  ): Promise<Record<string, unknown>> {
    try {
      return await this.executeToolViaRedisRpc(name, args, context);
    } catch (error) {
      this.logger.warn(
        `Redis RPC failed for ${name}, falling back to HTTP: ${(error as Error).message}`,
      );

      return this.internalAiClient.executeRemoteTool(
        name,
        args,
        this.buildToolContext(context),
        context.traceId,
      );
    }
  }

  /**
   * Executa uma tool publicando a requisição em Redis Streams e aguardando a resposta via BLPOP.
   * @param name - Nome da tool a ser executada.
   * @param args - Argumentos normalizados da tool.
   * @param context - Contexto operacional da run atual.
   * @returns Resultado retornado pelo worker que processou a requisição Redis.
   */
  private async executeToolViaRedisRpc(
    name: string,
    args: Record<string, unknown>,
    context: ToolExecutionContext,
  ): Promise<Record<string, unknown>> {
    const requestId = randomUUID();
    const replyKey = `ai:tool:reply:${context.tenantId}:${context.runId}:${requestId}`;

    await this.redisService.publishStream(
      this.gatewayConfigService.aiToolRequestStream,
      {
        request_id: requestId,
        tool_name: name,
        reply_key: replyKey,
        parameters: args,
        context: this.buildToolContext(context),
        requested_at: new Date().toISOString(),
      },
    );

    // Set TTL on reply key to prevent memory leak if response never arrives
    await this.redisService.getClient().expire(replyKey, 30);

    const response = await this.blockingPopJson(
      replyKey,
      this.toolRpcTimeoutMs,
      'tool_rpc_timeout',
    );

    return this.toObject(response);
  }

  /**
   * Constrói o contexto serializável da tool a partir das informações da run atual.
   * @param context - Contexto operacional da run atual.
   * @returns Mapa de campos gerado para encaminhar ao worker ou backend.
   */
  private buildToolContext(
    context: ToolExecutionContext,
  ): Record<string, unknown> {
    return {
      tenant_id: context.tenantId,
      run_id: context.runId,
      current_run_id: context.runId,
      agent_id: context.agentId,
      agent_role: context.agentRole ?? 'general',
      ticket_id: context.ticketId,
      trace_id: context.traceId,
      delegation_depth: context.delegationDepth,
      delegation_stack: context.delegationStack,
    };
  }

  /**
   * Resolve e mescla o contexto de entrada encaminhado durante a delegação.
   * @param args - Argumentos da tool contendo os campos de contexto da delegação.
   * @param context - Contexto operacional da run atual.
   * @returns Contexto combinado com campos obrigatórios preenchidos.
   */
  private resolveDelegationInputContext(
    args: Record<string, unknown>,
    context: ToolExecutionContext,
  ): Record<string, unknown> {
    const inputContext = {
      ...this.toObject(args['context']),
      ...this.toObject(args['input_context']),
    };

    if (!('ticket_id' in inputContext) && context.ticketId) {
      inputContext['ticket_id'] = context.ticketId;
    }

    if (!('trace_id' in inputContext) && context.traceId) {
      inputContext['trace_id'] = context.traceId;
    }

    if (!('body' in inputContext)) {
      const body =
        this.readString(args, 'body') ??
        this.readString(args, 'content') ??
        this.readString(args, 'message') ??
        this.readString(args, 'text') ??
        this.readString(args, 'prompt');

      if (body !== null) {
        inputContext['body'] = body;
      }
    }

    return inputContext;
  }

  /**
   * Publica um evento de delegação nos canais WebSocket da run atual.
   * @param eventName - Nome do evento publicado no canal de WebSocket.
   * @param context - Contexto operacional da run atual.
   * @param data - Dados adicionais incluídos no payload do evento.
   * @returns Promise resolvida após a publicação ou silenciada em caso de falha.
   */
  private async publishDelegationEvent(
    eventName: 'ai.run.delegating' | 'ai.run.delegated',
    context: ToolExecutionContext,
    data: Record<string, unknown>,
  ): Promise<void> {
    try {
      await this.redisService.publish(
        this.gatewayConfigService.wsEventsChannel,
        {
          event: eventName,
          tenant_id: context.tenantId,
          trace_id: context.traceId,
          rooms: [tenantRoom(context.tenantId), runRoom(context.runId)],
          data: {
            tenant_id: context.tenantId,
            run_id: context.runId,
            trace_id: context.traceId,
            ...data,
          },
        },
      );
    } catch (error) {
      this.logger.warn(
        `Failed to publish ${eventName}: ${(error as Error).message}`,
      );
    }
  }

  /**
   * Aguarda o resultado de uma run filha via BLPOP na chave de retorno de delegação.
   * @param parentRunId - Identificador da run pai que originou a delegação.
   * @returns Resultado retornado pela run filha após conclusão.
   */
  private async awaitDelegationResult(
    parentRunId: string,
  ): Promise<Record<string, unknown>> {
    const key = `ai:delegation:result:${parentRunId}`;
    const response = await this.blockingPopJson(
      key,
      this.delegationWaitTimeoutMs,
      'delegation_timeout',
    );

    return this.toObject(response);
  }

  /**
   * Realiza BLPOP em uma chave Redis e desserializa o resultado como JSON.
   * @param key - Chave Redis em que a resposta será aguardada.
   * @param timeoutMs - Tempo máximo de espera em milissegundos.
   * @param timeoutPrefix - Prefixo usado na mensagem de erro por timeout.
   * @returns Valor desserializado da resposta ou erro quando o tempo expira.
   */
  private async blockingPopJson(
    key: string,
    timeoutMs: number,
    timeoutPrefix: string,
  ): Promise<unknown> {
    const client =
      this.redisService.getBlockingClient() as unknown as RedisBlockingListClient;
    const timeoutSeconds = Math.max(1, Math.ceil(timeoutMs / 1000));
    const response = await client.blpop(key, timeoutSeconds);

    if (!response || response.length < 2) {
      throw new Error(`${timeoutPrefix} key=${key} timeout_ms=${timeoutMs}`);
    }

    try {
      return JSON.parse(response[1]) as unknown;
    } catch (error) {
      throw new Error(
        `invalid_redis_rpc_response key=${key} error=${(error as Error).message}`,
      );
    }
  }

  /**
   * Constrói a pilha de delegação deduplicada incluindo o agente atual.
   * @param args - Argumentos da tool contendo `delegation_stack` do payload.
   * @param context - Contexto operacional da run atual.
   * @returns Lista de agentes percorridos, sem duplicatas e com o agente atual ao fim.
   */
  private resolveDelegationStack(
    args: Record<string, unknown>,
    context: ToolExecutionContext,
  ): string[] {
    const rawStack = Array.isArray(args['delegation_stack'])
      ? args['delegation_stack']
      : (context.delegationStack ?? []);

    const normalized = rawStack
      .filter((item): item is string => typeof item === 'string' && item !== '')
      .filter((item, index, array) => array.indexOf(item) === index);

    if (!normalized.includes(context.agentId)) {
      normalized.push(context.agentId);
    }

    return normalized;
  }

  /**
   * Lê um campo como string de um registro, retornando `null` quando ausente ou inválido.
   * @param source - Registro de origem.
   * @param key - Nome do campo a ser lido.
   * @returns Valor string do campo ou `null`.
   */
  private readString(
    source: Record<string, unknown>,
    key: string,
  ): string | null {
    return getStringField(source, key) ?? null;
  }

  /**
   * Lê um campo como booleano de um registro, retornando o fallback quando ausente.
   * @param source - Registro de origem.
   * @param key - Nome do campo a ser lido.
   * @param fallback - Valor padrão retornado quando o campo não está presente.
   * @returns Valor booleano do campo ou o fallback informado.
   */
  private readBoolean(
    source: Record<string, unknown>,
    key: string,
    fallback: boolean,
  ): boolean {
    return getBooleanField(source, key) ?? fallback;
  }

  /**
   * Lê um campo como número de um registro, retornando o fallback quando ausente.
   * @param source - Registro de origem.
   * @param key - Nome do campo a ser lido.
   * @param fallback - Valor padrão retornado quando o campo não está presente.
   * @returns Valor numérico do campo ou o fallback informado.
   */
  private readNumber(
    source: Record<string, unknown>,
    key: string,
    fallback: number,
  ): number {
    return getNumberField(source, key) ?? fallback;
  }

  /**
   * Valida e retorna um timeout positivo, substituindo por fallback quando inválido.
   * @param value - Valor de timeout informado na configuração.
   * @param fallback - Valor padrão em milissegundos usado quando `value` é inválido.
   * @returns Timeout válido em milissegundos.
   */
  private resolveTimeout(value: number | undefined, fallback: number): number {
    if (typeof value === 'number' && Number.isFinite(value) && value > 0) {
      return value;
    }

    return fallback;
  }

  /**
   * Converte um valor desconhecido em objeto plain para serialização e transporte.
   * @param value - Valor de entrada potencialmente desconhecido.
   * @returns Objeto plain object derivado do valor informado.
   */
  private toObject(value: unknown): Record<string, unknown> {
    return toRecord(value);
  }
}
