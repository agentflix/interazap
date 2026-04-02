import { Injectable } from '@nestjs/common';
import { MetricsService } from '../../../metrics/metrics.service';

/**
 * Serviço responsável por centralizar métricas e estimativa de custo do domínio AI.
 */
@Injectable()
export class AiMetricsService {
  /** Tabela de preços por token em dólar com fallback para `gpt-4o-mini`. */
  private static readonly PRICING: Record<
    string,
    { inputPerToken: number; outputPerToken: number }
  > = {
    'gpt-4o': {
      inputPerToken: 5 / 1_000_000,
      outputPerToken: 15 / 1_000_000,
    },
    'gpt-4o-mini': {
      inputPerToken: 0.15 / 1_000_000,
      outputPerToken: 0.6 / 1_000_000,
    },
  };

  constructor(private readonly metricsService: MetricsService) {}

  /**
   * Registra a conclusão de uma run de AI com status final e duração total.
   * @param agentId - Identificador do agente executor.
   * @param model - Modelo utilizado na execução.
   * @param status - Status final registrado para a run.
   * @param durationSeconds - Duração total da execução em segundos.
   * @returns Não retorna valor.
   */
  recordRunCompleted(
    agentId: string,
    model: string,
    status: string,
    durationSeconds: number,
  ): void {
    this.metricsService.recordAutopilotRun(
      agentId,
      model,
      status,
      durationSeconds,
    );
  }

  /**
   * Registra o consumo de tokens de entrada e saída de uma completion.
   * @param agentId - Identificador do agente executor.
   * @param model - Modelo utilizado na completion.
   * @param promptTokens - Quantidade de tokens consumidos no prompt.
   * @param completionTokens - Quantidade de tokens consumidos na resposta.
   * @returns Não retorna valor.
   */
  recordTokenUsage(
    agentId: string,
    model: string,
    promptTokens: number,
    completionTokens: number,
  ): void {
    this.metricsService.recordAutopilotTokens(
      agentId,
      model,
      'input',
      promptTokens,
    );
    this.metricsService.recordAutopilotTokens(
      agentId,
      model,
      'output',
      completionTokens,
    );
  }

  /**
   * Estima e registra o custo monetário de uma completion com base na tabela interna.
   * @param agentId - Identificador do agente executor.
   * @param model - Modelo utilizado na completion.
   * @param promptTokens - Quantidade de tokens consumidos no prompt.
   * @param completionTokens - Quantidade de tokens consumidos na resposta.
   * @returns Não retorna valor.
   */
  recordRunCost(
    agentId: string,
    model: string,
    promptTokens: number,
    completionTokens: number,
  ): void {
    const cost = this.estimateCostDollars(
      model,
      promptTokens,
      completionTokens,
    );
    this.metricsService.recordAutopilotCost(agentId, model, cost);
  }

  /**
   * Calcula o custo estimado em dólar para um volume específico de tokens.
   * @param model - Modelo utilizado na execução.
   * @param promptTokens - Quantidade de tokens consumidos no prompt.
   * @param completionTokens - Quantidade de tokens consumidos na resposta.
   * @returns Valor estimado em dólar para a execução informada.
   */
  estimateCostDollars(
    model: string,
    promptTokens: number,
    completionTokens: number,
  ): number {
    const pricing =
      AiMetricsService.PRICING[model] ??
      AiMetricsService.PRICING['gpt-4o-mini'];

    return (
      promptTokens * pricing.inputPerToken +
      completionTokens * pricing.outputPerToken
    );
  }

  /**
   * Registra o resultado de uma chamada de tool.
   * @param agentId - Identificador do agente executor.
   * @param toolName - Nome da tool executada.
   * @param status - Resultado final da execução da tool.
   * @returns Não retorna valor.
   */
  recordToolCall(
    agentId: string,
    toolName: string,
    status: 'success' | 'error',
  ): void {
    this.metricsService.recordAutopilotToolCall(agentId, toolName, status);
  }

  /**
   * Registra o resultado de uma delegação entre agentes.
   * @param sourceAgentId - Agente que originou a delegação.
   * @param targetAgentId - Agente alvo da delegação.
   * @param status - Resultado final da delegação.
   * @returns Não retorna valor.
   */
  recordDelegation(
    sourceAgentId: string,
    targetAgentId: string,
    status: 'success' | 'error',
  ): void {
    this.metricsService.recordDelegation(sourceAgentId, targetAgentId, status);
  }

  /**
   * Registra a emissão de um chunk de streaming para um agente.
   * @param agentId - Identificador do agente que produziu o chunk.
   * @returns Não retorna valor.
   */
  recordStreamChunk(agentId: string): void {
    this.metricsService.recordStreamChunk(agentId);
  }

  /**
   * Registra acerto ou erro de cache na resolução de prompts.
   * @param hit - Indica se houve cache hit.
   * @returns Não retorna valor.
   */
  recordPromptCacheHit(hit: boolean): void {
    this.metricsService.recordCacheHit('prompt', hit);
  }

  /**
   * Registra acerto ou erro de cache na resolução de contexto.
   * @param hit - Indica se houve cache hit.
   * @returns Não retorna valor.
   */
  recordContextCacheHit(hit: boolean): void {
    this.metricsService.recordCacheHit('context', hit);
  }

  /**
   * Registra a quantidade total de iterações de tool call executadas em uma run.
   * @param agentId - Identificador do agente executor.
   * @param count - Quantidade de iterações realizadas.
   * @returns Não retorna valor.
   */
  recordIterationsPerRun(agentId: string, count: number): void {
    this.metricsService.recordAutopilotIterations(agentId, count);
  }

  /**
   * Registra o total de tokens consumidos ao longo de toda a run.
   * @param agentId - Identificador do agente executor.
   * @param model - Modelo utilizado na run.
   * @param tokens - Total acumulado de tokens consumidos.
   * @returns Não retorna valor.
   */
  recordTotalTokensPerRun(
    agentId: string,
    model: string,
    tokens: number,
  ): void {
    this.metricsService.recordAutopilotRunTokens(agentId, model, tokens);
  }

  /**
   * Registra uma saída antecipada da run com o motivo correspondente.
   * @param agentId - Identificador do agente executor.
   * @param reason - Motivo registrado para a saída antecipada.
   * @returns Não retorna valor.
   */
  recordEarlyExit(agentId: string, reason: string): void {
    this.metricsService.recordAutopilotEarlyExit(agentId, reason);
  }

  /**
   * Registra que uma completion foi truncada por limite de tokens.
   * @param agentId - Identificador do agente executor.
   * @returns Não retorna valor.
   */
  recordTruncatedResponse(agentId: string): void {
    this.metricsService.recordAutopilotTruncatedResponse(agentId);
  }
}
