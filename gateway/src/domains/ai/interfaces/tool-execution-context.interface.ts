/**
 * Contexto compartilhado entre os fluxos de execução de tools de AI.
 *
 * Carregado pelo orquestrador e repassado para cada estratégia de tool e
 * para os clientes internos durante toda a vida de uma run.
 */
export interface ToolExecutionContext {
  /** Identificador do tenant dono da run. */
  tenantId: string;
  /** Identificador principal da run em execução. */
  runId: string;
  /** Identificador de correlação da requisição de origem. */
  correlationId?: string;
  /** Identificador do agente executor da run. */
  agentId: string;
  /** Identificador opcional de rastreamento distribuído. */
  traceId?: string;
  /** Ticket relacionado à run, quando houver. */
  ticketId?: string;
  /** Agente alvo pré-configurado para delegação direta. */
  targetAgentId?: string;
  /** Papel lógico do agente executor. */
  agentRole?: string;
  /** Profundidade atual na pilha de delegações encadeadas. */
  delegationDepth?: number;
  /** Pilha de agentes já percorridos para detecção de ciclos. */
  delegationStack?: string[];
}
