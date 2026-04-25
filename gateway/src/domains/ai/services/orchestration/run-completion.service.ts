import { Logger } from '@nestjs/common';
import { GuardrailEvaluatorService } from '../guardrail-evaluator.service';
import { ToolExecutorService } from '../tool-executor.service';

/**
 * Handles post-completion side-effects such as guardrail evaluation,
 * implicit send_message injection, and final content sanitization.
 */
export class RunCompletionService {
  /**
   * @param guardrail     - GuardrailEvaluatorService for content safety checks
   * @param toolExecutor   - ToolExecutorService for implicit tool calls
   * @param logger         - NestJS Logger for instrumentation
   */
  constructor(
    private readonly guardrail: GuardrailEvaluatorService,
    private readonly toolExecutor: ToolExecutorService,
    private readonly logger: Logger,
  ) {}

  /**
   * Evaluates the final output, applies guardrail filtering, and injects
   * an implicit send_message call when the run produced content without one.
   *
   * @param completionContent       - Raw content returned by the AI
   * @param request                  - Original run request metadata
   * @param toolCalls                - Tool calls recorded during the run (mutated)
   * @param completionFinishReason   - Finish reason from the provider
   * @param earlyExitReason          - Reason the run exited early (e.g. delegation)
   * @returns Final content, guard status, and enriched tool calls
   */
  async finalize(
    completionContent: string,
    request: {
      tenantId: string;
      runId: string;
      traceId?: string;
      ticketId?: string;
      agentId?: string;
      agentRole?: string;
      delegationDepth?: number;
      delegationStack?: string[];
    },
    toolCalls: Array<Record<string, unknown>>,
    completionFinishReason: string | null,
    earlyExitReason: string | null,
  ): Promise<{
    finalContent: string;
    postGuardAllowed: boolean;
    toolCalls: Array<Record<string, unknown>>;
  }> {
    const normalizedContent =
      this.extractContentFromSendMessagePayload(completionContent) ??
      completionContent;
    const postGuard = this.guardrail.evaluateFinalOutput(normalizedContent);
    const finalContent = postGuard.allowed
      ? normalizedContent
      : 'Desculpe, não posso responder essa solicitação.';

    const sendMessageAlreadySent = toolCalls.some(
      (tc) =>
        tc['name'] === 'send_message' &&
        (tc['result'] as Record<string, unknown> | undefined)?.['success'] ===
          true,
    );

    if (
      !sendMessageAlreadySent &&
      finalContent.trim() !== '' &&
      request.ticketId &&
      earlyExitReason !== 'delegation_completed' &&
      completionFinishReason !== 'tool_calls'
    ) {
      const sendResult = await this.toolExecutor.executeTool(
        'send_message',
        {
          ticket_id: request.ticketId,
          content: finalContent,
        },
        {
          tenantId: request.tenantId,
          runId: request.runId,
          agentId: request.agentId ?? 'default',
          traceId: request.traceId,
          agentRole: request.agentRole,
          delegationDepth: request.delegationDepth,
          delegationStack: request.delegationStack,
          ticketId: request.ticketId,
        },
      );

      toolCalls.push({
        name: 'send_message',
        arguments: { ticket_id: request.ticketId, content: finalContent },
        result: sendResult,
        implicit: true,
      });

      if (sendResult['success'] !== true) {
        this.logger.error(
          `Mandatory send_message failed for run ${request.runId}: ${JSON.stringify(sendResult)}`,
        );
      }
    }

    return {
      finalContent,
      postGuardAllowed: postGuard.allowed,
      toolCalls,
    };
  }

  /**
   * Extrai texto humano de um payload JSON que represente uma tool call send_message.
   */
  private extractContentFromSendMessagePayload(content: string): string | null {
    try {
      const parsed = JSON.parse(content) as unknown;
      const candidate = Array.isArray(parsed) ? parsed[0] : parsed;
      if (!candidate || typeof candidate !== 'object') {
        return null;
      }

      const record = candidate as Record<string, unknown>;
      if (
        record.type === 'function' &&
        typeof record.function === 'object' &&
        !Array.isArray(record.function)
      ) {
        const fn = record.function as Record<string, unknown>;
        if (this.readOptionalString(fn.name) !== 'send_message') {
          return null;
        }

        return this.extractTextFromArguments(fn.arguments);
      }

      if (this.readOptionalString(record.name) !== 'send_message') {
        return null;
      }

      return this.extractTextFromArguments(record.arguments);
    } catch {
      return null;
    }
  }

  /**
   * Extrai o campo textual principal dos argumentos da send_message.
   */
  private extractTextFromArguments(argumentsValue: unknown): string | null {
    const args = this.toRecord(argumentsValue);
    const text =
      this.readOptionalString(args['content']) ??
      this.readOptionalString(args['text']) ??
      this.readOptionalString(args['message']) ??
      this.readOptionalString(args['body']);

    if (!text) {
      return null;
    }

    const trimmed = text.trim();
    return trimmed !== '' ? trimmed : null;
  }

  private toRecord(value: unknown): Record<string, unknown> {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
      return value as Record<string, unknown>;
    }

    if (typeof value === 'string') {
      try {
        const parsed = JSON.parse(value) as unknown;
        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
          return parsed as Record<string, unknown>;
        }
      } catch {
        return {};
      }
    }

    return {};
  }

  private readOptionalString(value: unknown): string | undefined {
    if (typeof value === 'string') {
      return value;
    }

    return undefined;
  }
}
