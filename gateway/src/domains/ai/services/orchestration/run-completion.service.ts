import { Logger } from '@nestjs/common';
import { GuardrailEvaluatorService } from '../guardrail-evaluator.service';
import { ToolExecutorService } from '../tool-executor.service';
import { BillingUsageClient } from '../../../billing/services/billing-usage-client.service';

/**
 * Serviço responsável por tratar os efeitos colaterais pós-completion de uma run.
 *
 * Contexto: avalia guardrails sobre a saída final, injeta implicitamente a tool
 * `send_message` quando necessário e sanitiza o conteúdo antes de retornar ao orquestrador.
 */
export class RunCompletionService {
  /**
   * @param guardrail           - GuardrailEvaluatorService para verificações de segurança de conteúdo
   * @param toolExecutor         - ToolExecutorService para chamadas implícitas de tools
   * @param logger               - NestJS Logger para instrumentação
   * @param billingUsageClient   - BillingUsageClient para verificações de cota de uso
   */
  constructor(
    private readonly guardrail: GuardrailEvaluatorService,
    private readonly toolExecutor: ToolExecutorService,
    private readonly logger: Logger,
    private readonly billingUsageClient?: BillingUsageClient,
  ) {}

  /**
   * Avalia a saída final, aplica filtragem de guardrail e injeta implicitamente
   * a tool `send_message` quando a run produziu conteúdo sem chamá-la.
   *
   * @param completionContent       - Conteúdo bruto retornado pela AI
   * @param request                  - Metadados originais da requisição da run
   * @param toolCalls                - Tool calls registradas durante a run (mutável)
   * @param completionFinishReason   - Motivo de término retornado pelo provider
   * @param earlyExitReason          - Motivo de saída antecipada da run (ex.: delegação)
   * @returns Conteúdo final, status do guardrail e tool calls enriquecidas
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
   * Extrai o texto legível de um payload JSON que represente uma tool call `send_message`.
   * @param content - Conteúdo da completion possivelmente serializado como JSON
   * @returns Texto extraído ou `null` quando não for um payload `send_message`
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
   * Verifica se alguma tool da lista foi executada com sucesso.
   * @param toolCalls - Tool calls registradas na run
   * @param names     - Nomes a verificar
   * @returns `true` quando ao menos uma tool da lista retornou `success: true`
   */
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

  /**
   * Verifica se uma delegação sem retorno (`return_after: false`) foi concluída com sucesso.
   * @param toolCalls - Tool calls registradas na run
   * @returns `true` quando uma delegação de handoff foi concluída
   */
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

  /**
   * Verifica se o conteúdo é um payload JSON serializado de uma tool call.
   * @param content - Conteúdo a ser verificado
   * @returns `true` quando o conteúdo representar um payload de tool call
   */
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

  /** Retorna a mensagem de fallback padrão quando o conteúdo é um payload serializado de tool call. */
  private defaultToolCallFallbackMessage(): string {
    return 'Perfeito, registrei seu interesse e vou acionar nosso time comercial para continuar com você.';
  }

  /**
   * Extrai o campo textual principal dos argumentos da tool `send_message`.
   * @param argumentsValue - Valor bruto dos argumentos (objeto ou JSON string)
   * @returns Texto extraído ou `null` quando nenhum campo textual for encontrado
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

  /**
   * Converte um valor desconhecido em objeto plain para serialização e leitura.
   * @param value - Valor de entrada potencialmente desconhecido
   * @returns Objeto derivado do valor, ou objeto vazio quando inválido
   */
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

  /**
   * Converte um valor desconhecido em string opcional para leitura segura de campos.
   * @param value - Valor de entrada de tipo desconhecido
   * @returns String quando o valor for uma string válida, `undefined` caso contrário
   */
  private readOptionalString(value: unknown): string | undefined {
    if (typeof value === 'string') {
      return value;
    }

    return undefined;
  }
}
