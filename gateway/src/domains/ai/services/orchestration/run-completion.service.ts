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
    const postGuard = this.guardrail.evaluateFinalOutput(completionContent);
    const finalContent = postGuard.allowed
      ? completionContent
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
}
