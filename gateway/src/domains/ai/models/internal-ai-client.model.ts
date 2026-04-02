/**
 * Modelos do cliente interno de IA.
 * Centraliza contratos de comunicação com a API interna do backend.
 */

/**
 * Envelope padrão de resposta da API interna do backend.
 */
export interface InternalApiEnvelope<T> {
  /** Indica se a operação foi processada com sucesso. */
  success?: boolean;
  /** Dados retornados pela operação executada. */
  data?: T;
}

/**
 * Payload da requisição de delegação de run para outro agente.
 */
export interface InternalAiDelegateRunPayload {
  /** Identificador do tenant dono da run. */
  tenant_id: string;
  /** Identificador da run pai que originou a delegação. */
  parent_run_id: string;
  /** Identificador do agente alvo da delegação. */
  target_agent_id: string;
  /** Pilha de agentes percorrida durante delegações anteriores. */
  delegation_stack: string[];
  /** Contexto de entrada encaminhado para o agente delegado. */
  input_context: Record<string, unknown>;
}
