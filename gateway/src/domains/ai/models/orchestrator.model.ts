/**
 * Modelos do domínio AI do gateway.
 * Reúne contratos de entrada consumidos pela orquestração de runs de AI.
 */

import type { AICompletionMessage } from './ai-completion.model';
import type { ContextSnapshot } from './context-window.model';
import type { PromptSnapshot } from './prompt.model';

/**
 * Representa os campos compartilhados entre entradas de orquestração.
 */
export interface OrchestratorRequestLike {
  /** Texto bruto enviado para a primeira interação com o modelo. */
  inputText?: string;
  /** Histórico de mensagens previamente montado para a execução. */
  messages?: AICompletionMessage[];
  /** Prompt de maior prioridade aplicado antes do prompt base. */
  superPrompt?: string;
  /** Prompt específico do segmento operacional. */
  segmentPrompt?: string;
  /** Prompt com instruções de planejamento da execução. */
  planPrompt?: string;
  /** Prompt principal do agente selecionado. */
  agentSystemPrompt?: string;
  /** Prompts adicionais vindos de arquivos do agente. */
  agentFilePrompts?: string[];
}

/**
 * Representa a entrada completa de uma run orquestrada no gateway.
 */
export interface OrchestratorRequest extends OrchestratorRequestLike {
  /** Identificador de correlação da requisição. */
  correlationId: string;
  /** Identificador do tenant associado à execução. */
  tenantId: string;
  /** Identificador principal da run em execução. */
  runId: string;
  /** Identificador opcional de rastreamento distribuído. */
  traceId?: string;
  /** Ticket relacionado à execução, quando houver. */
  ticketId?: string;
  /** Identificador do agente executor. */
  agentId?: string;
  /** Papel lógico do agente executor. */
  agentRole?: string;
  /** Profundidade atual da pilha de delegação. */
  delegationDepth?: number;
  /** Pilha de agentes percorrida durante delegações. */
  delegationStack?: string[];
  /** Provider solicitado para a execução. */
  provider?: string;
  /** Modelo solicitado para a run. */
  model?: string;
  /** Indica se a resposta final deve ser transmitida em streaming. */
  streamingEnabled?: boolean;
  /** Limite máximo de tokens por completion. */
  maxTokens?: number;
  /** Quantidade máxima de iterações de tool call. */
  maxToolIterations?: number;
  /** Orçamento total de tokens permitido para a run. */
  runTokenBudget?: number;
  /** Define se os resultados de tool devem ser compactados. */
  compactToolResults?: boolean;
  /** Snapshot já hidratado do prompt resolvido. */
  promptSnapshot?: PromptSnapshot;
  /** Snapshot já hidratado do contexto resolvido. */
  contextSnapshot?: ContextSnapshot;
  /** Snapshot opcional das tools previamente resolvidas. */
  toolsSnapshot?: unknown[];
}
