import { createHash } from 'crypto';
import { GuardrailEvaluatorService } from '../guardrail-evaluator.service';
import { ToolExecutorService } from '../tool-executor.service';
import { toRecord } from '../../../../shared/utils/payload-reader.util';
import { ToolExecutionContext } from '../../interfaces/tool-execution-context.interface';
import type { AICompletionMessage } from '../../models/ai-completion.model';
import type { PreparedToolCall } from '../../models/tool-call.model';

/**
 * Serviço responsável por validar, deduplicar e executar loops de tool calls.
 */
export class ToolCallLoopService {
  constructor(
    private readonly guardrail: GuardrailEvaluatorService,
    private readonly toolExecutor: ToolExecutorService,
    private readonly compactMaxDataLength: number,
  ) {}

  /**
   * Interpreta o conteúdo textual da completion e extrai tool calls serializadas em JSON.
   * @param content - Conteúdo retornado pelo modelo.
   * @returns Lista de chamadas de tool extraídas do payload.
   */
  parseToolCalls(content: string): Array<{ name: string; arguments: unknown }> {
    try {
      const parsed = JSON.parse(content) as unknown;
      const items =
        Array.isArray(parsed) && parsed.length > 0
          ? parsed
          : parsed && typeof parsed === 'object'
            ? [parsed]
            : [];

      return items
        .map((item) => {
          if (!item || typeof item !== 'object') {
            return null;
          }

          const record = item as Record<string, unknown>;
          if (
            record.type === 'function' &&
            typeof record.function === 'object'
          ) {
            const fn = record.function as Record<string, unknown>;

            return {
              name: this.readOptionalString(fn.name),
              arguments: this.parseToolArguments(fn.arguments),
            };
          }

          return {
            name: this.readOptionalString(record.name),
            arguments: this.parseToolArguments(record.arguments),
          };
        })
        .filter(
          (item): item is { name: string; arguments: unknown } =>
            item !== null &&
            typeof item.name === 'string' &&
            item.name.length > 0,
        );
    } catch {
      return [];
    }
  }

  /**
   * Gera um hash determinístico para deduplicar uma tool call com base no nome e argumentos.
   * @param name - Nome da tool avaliada.
   * @param args - Argumentos normalizados da tool.
   * @returns Hash SHA-256 da combinação entre nome e argumentos normalizados.
   */
  hashToolCall(name: string, args: Record<string, unknown>): string {
    const normalized = this.normalizeForHash(args);
    return createHash('sha256')
      .update(`${name}:${JSON.stringify(normalized)}`)
      .digest('hex');
  }

  /**
   * Executa em paralelo as tool calls elegíveis respeitando deduplicação e guardrails.
   * @param calls - Lista de tool calls preparadas para execução.
   * @param executedToolHashes - Conjunto com hashes já executados nesta run.
   * @param context - Contexto operacional da run atual.
   * @param shouldCompactToolResults - Indica se o resultado das tools deve ser compactado para contexto.
   * @returns Estrutura com resultados, mensagens a anexar e flags de fluxo especial.
   */
  async executeToolCallsParallel(
    calls: PreparedToolCall[],
    executedToolHashes: Set<string>,
    context: ToolExecutionContext,
    shouldCompactToolResults: boolean,
  ): Promise<{
    toolCalls: Array<Record<string, unknown>>;
    messagesToAppend: AICompletionMessage[];
    sendMessageSucceeded: boolean;
    delegationSucceeded: boolean;
  }> {
    const toolCalls: Array<Record<string, unknown>> = [];
    const messagesToAppend: AICompletionMessage[] = [];

    const eligibleCalls: PreparedToolCall[] = [];
    const preActions: Array<
      | { kind: 'skip'; record: Record<string, unknown> }
      | { kind: 'eligible'; eligibleIndex: number }
    > = [];

    for (const call of calls) {
      if (executedToolHashes.has(call.toolHash)) {
        preActions.push({
          kind: 'skip',
          record: {
            name: call.name,
            arguments: call.arguments,
            deduplicated: true,
          },
        });
        continue;
      }
      executedToolHashes.add(call.toolHash);

      const preTool = this.guardrail.evaluateToolCall(
        call.name,
        call.arguments,
      );
      if (!preTool.allowed) {
        preActions.push({
          kind: 'skip',
          record: { name: call.name, blocked: true, reason: preTool.reason },
        });
        continue;
      }

      const eligibleIndex = eligibleCalls.length;
      eligibleCalls.push(call);
      preActions.push({ kind: 'eligible', eligibleIndex });
    }

    const hasSendMessage = eligibleCalls.some((c) => c.name === 'send_message');
    const hasDelegation = eligibleCalls.some(
      (c) => c.name === 'delegate_to_agent',
    );
    const skippedByPriority = new Set<string>();

    // Priority order: delegate_to_agent > send_message > other tools.
    // Rationale: delegation is a control-transfer — any message from the router
    // becomes noise once the specialist takes over. When both are emitted in the
    // same turn (Router→Specialist pattern), keep only the delegation.
    if (hasDelegation) {
      for (const c of eligibleCalls) {
        if (c.name !== 'delegate_to_agent') {
          skippedByPriority.add(c.toolHash);
        }
      }
    } else if (hasSendMessage) {
      for (const c of eligibleCalls) {
        if (c.name !== 'send_message') {
          skippedByPriority.add(c.toolHash);
        }
      }
    }

    const callsToExecute: PreparedToolCall[] = [];
    const orderedActions: Array<
      { type: 'record'; record: Record<string, unknown> } | { type: 'execute' }
    > = [];

    for (const pre of preActions) {
      if (pre.kind === 'skip') {
        orderedActions.push({ type: 'record', record: pre.record });
      } else if (
        skippedByPriority.has(eligibleCalls[pre.eligibleIndex].toolHash)
      ) {
        const call = eligibleCalls[pre.eligibleIndex];
        orderedActions.push({
          type: 'record',
          record: {
            name: call.name,
            arguments: call.arguments,
            skipped_priority_guardrail: true,
          },
        });
      } else {
        callsToExecute.push(eligibleCalls[pre.eligibleIndex]);
        orderedActions.push({ type: 'execute' });
      }
    }

    const settled = await Promise.allSettled(
      callsToExecute.map((call) =>
        this.toolExecutor.executeTool(call.name, call.arguments, context),
      ),
    );

    let executionIndex = 0;
    let sendMessageSucceeded = false;
    let delegationSucceeded = false;

    for (const action of orderedActions) {
      if (action.type === 'record') {
        toolCalls.push(action.record);
        continue;
      }

      const call = callsToExecute[executionIndex];
      const settledResult = settled[executionIndex];
      executionIndex += 1;

      const toolResult =
        settledResult.status === 'fulfilled'
          ? settledResult.value
          : this.toToolExecutionError(settledResult.reason);

      if (call.name === 'send_message' && toolResult['success'] === true) {
        sendMessageSucceeded = true;
      }

      if (
        call.name === 'delegate_to_agent' &&
        toolResult['success'] === true &&
        toolResult['delegated'] === true
      ) {
        delegationSucceeded = true;
      }

      const toolResultForContext = shouldCompactToolResults
        ? this.compactToolResult(toolResult)
        : toolResult;

      toolCalls.push({
        name: call.name,
        arguments: call.arguments,
        result: toolResult,
      });

      messagesToAppend.push({
        role: 'assistant',
        content: JSON.stringify({
          name: call.name,
          arguments: call.arguments,
        }),
      });
      messagesToAppend.push({
        role: 'system',
        content: `tool_result:${JSON.stringify(toolResultForContext)}`,
      });
    }

    return {
      toolCalls,
      messagesToAppend,
      sendMessageSucceeded,
      delegationSucceeded,
    };
  }

  /**
   * Normaliza recursivamente um valor para produção de hash estável.
   * @param input - Valor de entrada a ser normalizado.
   * @returns Valor com chaves de objetos ordenadas alfabeticamente para serialização determinista.
   */
  private normalizeForHash(input: unknown): unknown {
    if (Array.isArray(input)) {
      return input.map((item) => this.normalizeForHash(item));
    }

    if (input && typeof input === 'object') {
      const entries = Object.entries(input as Record<string, unknown>).sort(
        ([left], [right]) => left.localeCompare(right),
      );
      const normalized: Record<string, unknown> = {};

      for (const [key, value] of entries) {
        normalized[key] = this.normalizeForHash(value);
      }

      return normalized;
    }

    return input;
  }

  /**
   * Converte o motivo de rejeição de uma Promise em registro de erro padronizado.
   * @param reason - Motivo capturado na rejeição da Promise.
   * @returns Registro de erro com `success: false` e detalhes do problema.
   */
  private toToolExecutionError(reason: unknown): Record<string, unknown> {
    if (reason instanceof Error) {
      return {
        success: false,
        error: reason.message,
      };
    }

    if (typeof reason === 'string') {
      return {
        success: false,
        error: reason,
      };
    }

    return {
      success: false,
      error: 'Tool execution failed',
      details: this.serializeUnknown(reason),
    };
  }

  /**
   * Serializa um valor desconhecido de forma segura para uso em mensagens de log.
   * @param value - Valor de entrada potencialmente não serializável.
   * @returns Representação em string do valor informado.
   */
  private serializeUnknown(value: unknown): string {
    try {
      return JSON.stringify(value);
    } catch {
      return String(value);
    }
  }

  /**
   * Compacta o resultado de uma tool retendo apenas os campos essenciais para a janela de contexto.
   * @param result - Resultado completo retornado pela execução da tool.
   * @returns Resultado reduzido com `success`, `error` e `data` truncado quando necessário.
   */
  private compactToolResult(
    result: Record<string, unknown>,
  ): Record<string, unknown> {
    const compacted: Record<string, unknown> = {
      success: result['success'],
    };

    if (result['error'] !== undefined) {
      compacted['error'] = result['error'];
    }

    if (result['data'] !== undefined) {
      const serializedData = JSON.stringify(result['data']);
      if (serializedData.length > this.compactMaxDataLength) {
        compacted['data'] =
          `${serializedData.slice(0, this.compactMaxDataLength)}...[truncated]`;
      } else {
        compacted['data'] = result['data'];
      }
    }

    return compacted;
  }

  /**
   * Converte um valor desconhecido em string opcional para leitura segura de campos.
   * @param value - Valor de entrada de tipo desconhecido.
   * @returns String quando o valor é uma string válida, `undefined` caso contrário.
   */
  private readOptionalString(value: unknown): string | undefined {
    if (typeof value === 'string') {
      return value;
    }

    return undefined;
  }

  /**
   * Parseia argumentos de tool call quando vierem como objeto ou JSON serializado.
   */
  private parseToolArguments(value: unknown): unknown {
    if (typeof value !== 'string') {
      return value ?? {};
    }

    try {
      return JSON.parse(value) as unknown;
    } catch {
      return {};
    }
  }

  /**
   * Converte um valor desconhecido em objeto simples para serialização e transporte.
   * @param input - Valor de entrada potencialmente desconhecido.
   * @returns Objeto plain object derivado do valor informado.
   */
  toObject(input: unknown): Record<string, unknown> {
    return toRecord(input);
  }
}
