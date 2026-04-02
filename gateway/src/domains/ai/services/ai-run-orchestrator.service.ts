import { Injectable, Logger } from '@nestjs/common';
import { OpenAIProviderAdapter } from '../providers/openai/openai-provider.adapter';
import { PromptAssemblerService } from './prompt-assembler.service';
import { ContextWindowService } from './context-window.service';
import { ToolExecutorService } from './tool-executor.service';
import { GuardrailEvaluatorService } from './guardrail-evaluator.service';
import { StreamHandlerService } from './stream-handler.service';
import { InternalAiClientService } from './internal-ai-client.service';
import { AiMetricsService } from './ai-metrics.service';
import { toRecord } from '../../../shared/utils/payload-reader.util';
import { MessageBuilderService } from './orchestration/message-builder.service';
import { ToolCallLoopService } from './orchestration/tool-call-loop.service';
import { RunCompletionService } from './orchestration/run-completion.service';
import { ToolExecutionContext } from '../interfaces/tool-execution-context.interface';
import type { AICompletionRequest } from '../models/ai-completion.model';
import type { OrchestratorRequest } from '../models/orchestrator.model';
import type { PreparedToolCall } from '../models/tool-call.model';

/**
 * Serviço responsável por orquestrar a execução completa de uma run de AI no gateway.
 */
@Injectable()
export class AiRunOrchestratorService {
  private static readonly DEFAULT_MAX_TOKENS = 800;
  private static readonly DEFAULT_MAX_TOOL_ITERATIONS = 5;
  private static readonly DEFAULT_RUN_TOKEN_BUDGET = 32000;
  private static readonly COMPACT_MAX_DATA_LENGTH = 500;
  private static readonly MAX_TOOL_HISTORY_MESSAGES = 10;

  private readonly logger = new Logger(AiRunOrchestratorService.name);
  private readonly messageBuilder = new MessageBuilderService();
  private readonly toolCallLoop: ToolCallLoopService;
  private readonly runCompletion: RunCompletionService;

  constructor(
    private readonly openaiProvider: OpenAIProviderAdapter,
    private readonly promptAssembler: PromptAssemblerService,
    private readonly contextWindow: ContextWindowService,
    private readonly internalAiClient: InternalAiClientService,
    private readonly toolExecutor: ToolExecutorService,
    private readonly guardrail: GuardrailEvaluatorService,
    private readonly streamHandler: StreamHandlerService,
    private readonly aiMetrics: AiMetricsService,
  ) {
    this.toolCallLoop = new ToolCallLoopService(
      this.guardrail,
      this.toolExecutor,
      AiRunOrchestratorService.COMPACT_MAX_DATA_LENGTH,
    );
    this.runCompletion = new RunCompletionService(
      this.guardrail,
      this.toolExecutor,
      this.logger,
    );
  }

  /**
   * Executa uma run de AI com hidratação de prompt, contexto, tools e iterações de tool call.
   * @param request - Requisição normalizada da run a ser processada.
   * @returns Payload final da execução com status, saída e métricas agregadas.
   */
  async execute(
    request: OrchestratorRequest,
  ): Promise<Record<string, unknown>> {
    const startedAt = Date.now();
    const agentId = request.agentId ?? 'default';
    const runId = request.runId || request.correlationId;
    const maxTokens = this.resolvePositiveInteger(
      request.maxTokens,
      AiRunOrchestratorService.DEFAULT_MAX_TOKENS,
    );
    const maxToolIterations = this.resolvePositiveInteger(
      request.maxToolIterations,
      AiRunOrchestratorService.DEFAULT_MAX_TOOL_ITERATIONS,
    );
    const runTokenBudget = this.resolvePositiveInteger(
      request.runTokenBudget,
      AiRunOrchestratorService.DEFAULT_RUN_TOKEN_BUDGET,
    );
    const shouldCompactToolResults = request.compactToolResults !== false;

    const prefetchStart = Date.now();
    const hydratedAt =
      request.promptSnapshot?.hydrated_at ??
      request.promptSnapshot?.hydratedAt ??
      request.contextSnapshot?.hydrated_at ??
      request.contextSnapshot?.hydratedAt;

    const promptSnapshot = request.promptSnapshot?.prompt
      ? {
          ...request.promptSnapshot,
          hydrated_at: hydratedAt,
        }
      : undefined;

    const contextSnapshot = request.contextSnapshot?.context
      ? {
          ...request.contextSnapshot,
          hydrated_at: hydratedAt,
        }
      : undefined;

    let toolsPromise: Promise<unknown[]>;
    if (
      Array.isArray(request.toolsSnapshot) &&
      request.toolsSnapshot.length > 0
    ) {
      toolsPromise = Promise.resolve(request.toolsSnapshot);
    } else if (request.agentId) {
      toolsPromise = this.internalAiClient
        .fetchToolsCached(request.agentId, request.traceId)
        .then((tools) => tools as unknown[]);
    } else {
      toolsPromise = Promise.resolve([]);
    }

    const [prompt, context, tools]: [
      string,
      Record<string, unknown>,
      unknown[],
    ] = await Promise.all([
      this.promptAssembler.resolvePrompt(
        request.tenantId,
        request.traceId,
        promptSnapshot,
      ),
      request.ticketId
        ? this.contextWindow.resolveContext(
            request.ticketId,
            request.traceId,
            contextSnapshot,
          )
        : Promise.resolve({} as Record<string, unknown>),
      toolsPromise,
    ]);
    this.logger.debug(
      `Prefetch (prompt+context+tools) completed in ${Date.now() - prefetchStart}ms`,
    );
    const composedPrompt = this.composeFirstCallPrompt(prompt, request);

    const normalizedTools = (tools ?? [])
      .map((tool) => this.toFunctionTool(tool))
      .filter(
        (
          tool,
        ): tool is {
          name: string;
          description: string;
          parameters: Record<string, unknown>;
        } => tool !== null,
      );

    const openAiTools = normalizedTools.map((tool) => ({
      type: 'function' as const,
      function: {
        name: tool.name,
        description: tool.description,
        parameters: tool.parameters,
      },
    }));

    const messages = this.buildMessages(
      composedPrompt,
      context,
      request,
      tools,
    );

    const completionRequest: AICompletionRequest = {
      messages,
      stream: request.streamingEnabled === true,
      temperature: 0.3,
      maxTokens,
      ...(request.model ? { model: request.model } : {}),
      ...(openAiTools.length > 0 ? { tools: openAiTools } : {}),
    };

    let completion = await this.completeOnce(completionRequest);
    let totalTokensUsed = completion.totalTokens;
    let earlyExitReason: string | null = null;
    const toolCalls: Array<Record<string, unknown>> = [];
    const executedToolHashes = new Set<string>();
    const executionContext: ToolExecutionContext = {
      tenantId: request.tenantId,
      runId,
      agentId,
      traceId: request.traceId,
      agentRole: request.agentRole,
      delegationDepth: request.delegationDepth,
      delegationStack: request.delegationStack,
      ticketId: request.ticketId,
    };

    const baseMessageCount = completionRequest.messages.length;
    let loopCount = 0;
    while (
      completion.finishReason === 'tool_calls' &&
      loopCount < maxToolIterations
    ) {
      loopCount += 1;

      if (totalTokensUsed >= runTokenBudget) {
        const parsedCalls = this.parseToolCalls(completion.content);
        const sendMessageCalls = parsedCalls.filter(
          (call) => String(call.name ?? '') === 'send_message',
        );

        const sendMessagePreparedCalls = sendMessageCalls.map((call) => ({
          name: 'send_message',
          arguments: this.toObject(call.arguments),
          toolHash: this.hashToolCall(
            'send_message',
            this.toObject(call.arguments),
          ),
        }));

        const sendMessageExecution = await this.executeToolCallsParallel(
          sendMessagePreparedCalls,
          executedToolHashes,
          executionContext,
          true,
        );

        toolCalls.push(...sendMessageExecution.toolCalls);

        earlyExitReason = sendMessageExecution.sendMessageSucceeded
          ? 'send_message_completed'
          : 'token_budget_exceeded';
        this.logger.warn(
          `Run ${runId} token budget exceeded before iteration ${loopCount}`,
        );
        break;
      }

      const parsedCalls = this.parseToolCalls(completion.content);
      if (parsedCalls.length === 0) {
        break;
      }

      const preparedCalls = parsedCalls.map((call) => {
        const toolName = String(call.name ?? '');
        const args = this.toObject(call.arguments);

        return {
          name: toolName,
          arguments: args,
          toolHash: this.hashToolCall(toolName, args),
        };
      });

      const execution = await this.executeToolCallsParallel(
        preparedCalls,
        executedToolHashes,
        executionContext,
        shouldCompactToolResults,
      );

      toolCalls.push(...execution.toolCalls);
      completionRequest.messages.push(...execution.messagesToAppend);

      // Sliding window: keep base messages + only recent tool history
      const toolMessages = completionRequest.messages.slice(baseMessageCount);
      if (
        toolMessages.length > AiRunOrchestratorService.MAX_TOOL_HISTORY_MESSAGES
      ) {
        const trimmed = toolMessages.slice(
          toolMessages.length -
            AiRunOrchestratorService.MAX_TOOL_HISTORY_MESSAGES,
        );
        completionRequest.messages = [
          ...completionRequest.messages.slice(0, baseMessageCount),
          ...trimmed,
        ];
      }

      if (execution.sendMessageSucceeded) {
        earlyExitReason = 'send_message_completed';
        break;
      }

      if (execution.delegationSucceeded) {
        earlyExitReason = 'delegation_completed';
        break;
      }

      // Após primeira iteração, filtrar tools para apenas as usadas + send_message
      if (
        loopCount > 0 &&
        completionRequest.tools &&
        completionRequest.tools.length > 0
      ) {
        const usedToolNames = new Set<string>(preparedCalls.map((c) => c.name));
        usedToolNames.add('send_message');
        const relevantTools = completionRequest.tools.filter((t) =>
          usedToolNames.has(t.function?.name ?? ''),
        );
        if (relevantTools.length > 0) {
          completionRequest.tools = relevantTools;
        }
      }

      completionRequest.stream = false;
      completion = await this.completeOnce(completionRequest);
      totalTokensUsed += completion.totalTokens;
    }

    if (
      earlyExitReason === null &&
      completion.finishReason === 'tool_calls' &&
      loopCount >= maxToolIterations
    ) {
      earlyExitReason = 'max_iterations_reached';
    }

    if (
      earlyExitReason === 'max_iterations_reached' &&
      completion.finishReason === 'tool_calls'
    ) {
      this.logger.warn(
        `Run ${runId} reached max iterations with pending tool_calls; requesting text-only fallback`,
      );
      const fallbackRequest: AICompletionRequest = {
        messages: completionRequest.messages,
        model: completionRequest.model,
        stream: false,
        temperature: completionRequest.temperature,
        maxTokens: completionRequest.maxTokens,
      };
      completion = await this.completeOnce(fallbackRequest);
      totalTokensUsed += completion.totalTokens;
      earlyExitReason = 'max_iterations_fallback';
    }

    if (completion.finishReason === 'length') {
      this.logger.warn(`Completion truncated by max_tokens for run ${runId}`);
      this.aiMetrics.recordTruncatedResponse(agentId);
    }

    if (request.streamingEnabled === true && request.tenantId) {
      await this.streamChunks(
        request.tenantId,
        runId,
        agentId,
        completion.content,
        request.traceId,
      );
      await this.streamHandler.emitFinal(
        request.tenantId,
        runId,
        completion.content,
        request.traceId,
      );
    }

    const completionResult = await this.runCompletion.finalize(
      completion.content,
      {
        tenantId: request.tenantId,
        runId,
        traceId: request.traceId,
        ticketId: request.ticketId,
        agentId,
        agentRole: request.agentRole,
        delegationDepth: request.delegationDepth,
        delegationStack: request.delegationStack,
      },
      toolCalls,
      completion.finishReason,
      earlyExitReason,
    );

    const durationSeconds = (Date.now() - startedAt) / 1000;
    this.aiMetrics.recordRunCompleted(
      agentId,
      completion.model,
      completionResult.postGuardAllowed ? 'success' : 'blocked',
      durationSeconds,
    );
    this.aiMetrics.recordTokenUsage(
      agentId,
      completion.model,
      completion.promptTokens,
      completion.completionTokens,
    );
    this.aiMetrics.recordRunCost(
      agentId,
      completion.model,
      completion.promptTokens,
      completion.completionTokens,
    );
    this.aiMetrics.recordIterationsPerRun(agentId, loopCount);
    this.aiMetrics.recordTotalTokensPerRun(
      agentId,
      completion.model,
      totalTokensUsed,
    );
    if (earlyExitReason !== null) {
      this.aiMetrics.recordEarlyExit(agentId, earlyExitReason);
    }

    return {
      run_id: runId,
      tenant_id: request.tenantId,
      ticket_id: request.ticketId,
      agent_id: request.agentId,
      iterations_count: loopCount,
      total_tokens_used: totalTokensUsed,
      early_exit_reason: earlyExitReason,
      status: completionResult.postGuardAllowed ? 'completed' : 'blocked',
      output: {
        content: completionResult.finalContent,
        model: completion.model,
        finish_reason: completion.finishReason,
        tool_calls: completionResult.toolCalls,
      },
      prompt_tokens: completion.promptTokens,
      completion_tokens: completion.completionTokens,
      total_tokens: completion.totalTokens,
      trace_id: request.traceId,
      streaming_enabled: request.streamingEnabled === true,
      classifier_result: null,
      completed_at: new Date().toISOString(),
    };
  }

  /**
   * Executa uma única chamada ao provider de AI e retorna o resultado normalizado.
   * @param completionRequest - Requisição de completion a ser enviada ao provider.
   * @returns Resposta normalizada com conteúdo, modelo, tokens e motivo de término.
   */
  private async completeOnce(completionRequest: AICompletionRequest): Promise<{
    content: string;
    model: string;
    finishReason: 'stop' | 'length' | 'content_filter' | 'tool_calls' | null;
    promptTokens: number;
    completionTokens: number;
    totalTokens: number;
  }> {
    return this.openaiProvider.complete(completionRequest);
  }

  /**
   * Monta o histórico de mensagens delegando ao `MessageBuilderService`.
   * @param prompt - Prompt base já resolvido para a execução.
   * @param context - Contexto estruturado disponível para a run.
   * @param request - Requisição de orquestração contendo histórico e prompts adicionais.
   * @param tools - Lista bruta de tools disponíveis para a execução.
   * @returns Histórico final de mensagens normalizado para envio ao modelo.
   */
  private buildMessages(
    prompt: string,
    context: Record<string, unknown>,
    request: OrchestratorRequest,
    tools: unknown[],
  ): Array<{ role: 'system' | 'user' | 'assistant'; content: string }> {
    return this.messageBuilder.buildMessages(prompt, context, request, tools);
  }

  /**
   * Compõe o prompt da primeira chamada combinando camadas de tier e arquivos do agente.
   * @param basePrompt - Prompt base resolvido para a execução.
   * @param request - Requisição contendo prompts complementares do agente.
   * @returns Prompt final composto para a primeira chamada ao modelo.
   */
  private composeFirstCallPrompt(
    basePrompt: string,
    request: OrchestratorRequest,
  ): string {
    return this.messageBuilder.composeFirstCallPrompt(basePrompt, request);
  }

  /**
   * Interpreta o conteúdo da completion e extrai tool calls serializadas em JSON.
   * @param content - Conteúdo retornado pelo modelo.
   * @returns Lista de chamadas de tool extraídas do payload.
   */
  private parseToolCalls(
    content: string,
  ): Array<{ name: string | undefined; arguments: unknown }> {
    return this.toolCallLoop.parseToolCalls(content);
  }

  /**
   * Converte um valor desconhecido em objeto plain para serialização e transporte.
   * @param input - Valor de entrada potencialmente desconhecido.
   * @returns Objeto plain object derivado do valor informado.
   */
  private toObject(input: unknown): Record<string, unknown> {
    return toRecord(input);
  }

  /**
   * Converte um valor para inteiro positivo finito, usando fallback quando inválido.
   * @param value - Valor a ser validado e convertido.
   * @param fallback - Valor padrão retornado quando `value` é inválido.
   * @returns Inteiro positivo válido derivado de `value` ou o `fallback`.
   */
  private resolvePositiveInteger(value: unknown, fallback: number): number {
    if (typeof value === 'number' && Number.isFinite(value) && value > 0) {
      return Math.trunc(value);
    }

    if (typeof value === 'string') {
      const parsed = Number(value);
      if (Number.isFinite(parsed) && parsed > 0) {
        return Math.trunc(parsed);
      }
    }

    return fallback;
  }

  /**
   * Normaliza uma definição de tool para o formato de função aceito pela completion.
   * @param tool - Definição bruta de tool recebida da API interna ou do snapshot.
   * @returns Tool normalizada ou `null` quando o valor informado é inválido.
   */
  private toFunctionTool(tool: unknown): {
    name: string;
    description: string;
    parameters: Record<string, unknown>;
  } | null {
    return this.messageBuilder.toFunctionTool(tool);
  }

  /**
   * Lista os nomes das tools válidas disponíveis para a execução.
   * @param tools - Coleção bruta de tools a ser inspecionada.
   * @returns Lista somente com os nomes das tools válidas.
   */
  private listToolNames(tools: unknown[]): string[] {
    return this.messageBuilder.listToolNames(tools);
  }

  /**
   * Gera um hash determinístico para deduplicar uma tool call com base no nome e argumentos.
   * @param name - Nome da tool avaliada.
   * @param args - Argumentos normalizados da tool.
   * @returns Hash SHA-256 da combinação entre nome e argumentos normalizados.
   */
  private hashToolCall(name: string, args: Record<string, unknown>): string {
    return this.toolCallLoop.hashToolCall(name, args);
  }

  /**
   * Executa em paralelo as tool calls elegíveis respeitando deduplicao e guardrails.
   * @param calls - Tool calls preparadas para execução.
   * @param executedToolHashes - Conjunto com hashes já executados nesta run.
   * @param context - Contexto operacional da run atual.
   * @param shouldCompactToolResults - Indica se o resultado das tools deve ser compactado.
   * @returns Estrutura com resultados, mensagens a anexar e flags de fluxo especial.
   */
  private async executeToolCallsParallel(
    calls: PreparedToolCall[],
    executedToolHashes: Set<string>,
    context: ToolExecutionContext,
    shouldCompactToolResults: boolean,
  ): Promise<{
    toolCalls: Array<Record<string, unknown>>;
    messagesToAppend: Array<{
      role: 'system' | 'user' | 'assistant';
      content: string;
    }>;
    sendMessageSucceeded: boolean;
    delegationSucceeded: boolean;
  }> {
    return this.toolCallLoop.executeToolCallsParallel(
      calls,
      executedToolHashes,
      context,
      shouldCompactToolResults,
    );
  }

  /**
   * Simula streaming fragmentando o conteúdo final em chunks e emitindo via WebSocket.
   * @param tenantId - Identificador do tenant dono da execução.
   * @param runId - Identificador da run em andamento.
   * @param agentId - Identificador do agente que produziu o conteúdo.
   * @param content - Conteúdo final a ser fragmentado e transmitido.
   * @param traceId - Identificador opcional de rastreamento distribuído.
   * @returns Promise resolvida após a emissão de todos os chunks.
   */
  private async streamChunks(
    tenantId: string,
    runId: string,
    agentId: string,
    content: string,
    traceId?: string,
  ): Promise<void> {
    const chunkSize = 512;
    let index = 0;

    while (index < content.length) {
      let end = Math.min(index + chunkSize, content.length);

      // Evitar cortar no meio de surrogate pair
      if (
        end < content.length &&
        content.charCodeAt(end - 1) >= 0xd800 &&
        content.charCodeAt(end - 1) <= 0xdbff
      ) {
        end++;
      }

      const chunk = content.slice(index, end);
      await this.streamHandler.emitChunk(
        tenantId,
        runId,
        agentId,
        chunk,
        traceId,
      );
      index = end;
    }
  }
}
