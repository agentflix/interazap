/**
 * Modelos do domínio AI do gateway.
 * Reúne contratos usados no loop de execução de tool calls.
 */

/**
 * Representa uma tool call já preparada para validação, deduplicação e execução.
 */
export interface PreparedToolCall {
  /** Nome da tool que será executada. */
  name: string;
  /** Argumentos normalizados da tool. */
  arguments: Record<string, unknown>;
  /** Hash determinístico usado para deduplicar execuções. */
  toolHash: string;
}
