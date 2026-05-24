import { Logger } from '@nestjs/common';
import { GuardrailEvaluatorService } from '../guardrail-evaluator.service';
import { ToolExecutorService } from '../tool-executor.service';
import { BillingUsageClient } from '../../../billing/services/billing-usage-client.service';

/**
 * Handles post-completion side-effects such as guardrail evaluation,
 * implicit send_message injection, and final content sanitization.
 */
export class RunCompletionService {
  /**
   * @param guardrail           - GuardrailEvaluatorService for content safety checks
   * @param toolExecutor         - ToolExecutorService for implicit tool calls
   * @param logger               - NestJS Logger for instrumentation
   * @param billingUsageClient   - BillingUsageClient for quota checks
   */
  constructor(
    private readonly guardrail: GuardrailEvaluatorService,
    private readonly toolExecutor: ToolExecutorService,
    private readonly logger: Logger,
    private readonly billingUsageClient?: BillingUsageClient,
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
      aiTurnId?: string;
    },
    toolCalls: Array<Record<string, unknown>>,
    completionFinishReason: string | null,
    earlyExitReason: string | null,
  ): Promise<{
    finalContent: string;
    postGuardAllowed: boolean;
    toolCalls: Array<Record<string, unknown>>;
    blockedByQuota: boolean;
  }> {
    const normalizedContent =
      this.extractContentFromSendMessagePayload(completionContent) ??
      completionContent;
    const shouldUseToolFallback =
      this.isSerializedToolCallPayload(normalizedContent) ||
      (completionFinishReason === 'tool_calls' &&
        normalizedContent.trim() === '');
    const safeContent = shouldUseToolFallback
      ? this.defaultToolCallFallbackMessage()
      : normalizedContent;
    const postGuard = this.guardrail.evaluateFinalOutput(safeContent);
    const finalContent = postGuard.allowed
      ? safeContent
      : 'Desculpe, não posso responder essa solicitação.';

    const sendMessageAlreadySent = this.hasSuccessfulToolCall(toolCalls, [
      'send_message',
    ]);
    const terminalActionAlreadyCompleted =
      this.hasSuccessfulToolCall(toolCalls, [
        'transfer_to_human',
        'close_ticket',
      ]) || this.hasCompletedHandoff(toolCalls);

    let blockedByQuota = false;

    if (
      !sendMessageAlreadySent &&
      !terminalActionAlreadyCompleted &&
      finalContent.trim() !== '' &&
      request.ticketId &&
      earlyExitReason !== 'delegation_completed'
    ) {
      // Check billing quota before sending the AI message
      let quotaCheck: { allowed: boolean } | undefined;
      if (!this.billingUsageClient) {
        this.logger.warn(
          `BillingUsageClient not provided — quota check skipped for run ${request.runId}`,
        );
      }
      if (this.billingUsageClient && request.aiTurnId) {
        quotaCheck = await this.billingUsageClient.checkAndIncrement(
          request.tenantId,
          'whatsapp',
          request.aiTurnId,
        );
      }

      if (quotaCheck && !quotaCheck.allowed) {
        blockedByQuota = true;
        this.logger.warn(
          `Run ${request.runId} blocked by quota for tenant ${request.tenantId}`,
        );

        const handoffResult = await this.toolExecutor.executeTool(
          'transfer_to_human',
          { ticket_id: request.ticketId, reason: 'quota_exceeded' },
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
          name: 'transfer_to_human',
          arguments: { ticket_id: request.ticketId, reason: 'quota_exceeded' },
          result: handoffResult,
          implicit: true,
          blocked_by_quota: true,
        });
      } else {
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
    }

    return {
      finalContent,
      postGuardAllowed: postGuard.allowed,
      toolCalls,
      blockedByQuota,
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

  private hasSuccessfulToolCall(
    toolCalls: Array<Record<string, unknown>>,
    names: string[],
  ): boolean {
    return toolCalls.some((tc) => {
      if (!names.includes((tc['name'] as string) ?? '')) {
        return false;
      }

      const result = this.toRecord(tc['result']);
      return result['success'] === true;
    });
  }

  private hasCompletedHandoff(
    toolCalls: Array<Record<string, unknown>>,
  ): boolean {
    return toolCalls.some((tc) => {
      if (tc['name'] !== 'delegate_to_agent') {
        return false;
      }

      const result = this.toRecord(tc['result']);
      if (result['success'] !== true) {
        return false;
      }

      const data = this.toRecord(result['data']);
      const args = this.toRecord(tc['arguments']);

      return (
        data['return_after'] === false ||
        args['return_after'] === false ||
        data['handoff'] === true
      );
    });
  }

  private isSerializedToolCallPayload(content: string): boolean {
    try {
      const parsed = JSON.parse(content) as unknown;
      const candidate = Array.isArray(parsed) ? parsed[0] : parsed;
      if (!candidate || typeof candidate !== 'object') {
        return false;
      }

      const record = candidate as Record<string, unknown>;
      if (record.type === 'function' && typeof record.function === 'object') {
        return true;
      }

      return (
        typeof record.name === 'string' &&
        (record.arguments !== undefined || record.result !== undefined)
      );
    } catch {
      return false;
    }
  }

  private defaultToolCallFallbackMessage(): string {
    return 'Perfeito, registrei seu interesse e vou acionar nosso time comercial para continuar com você.';
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
