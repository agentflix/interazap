/**
 * Modelos do domínio AI do gateway.
 * Define a representação das tools expostas pela API interna.
 */

/**
 * Representa uma tool disponibilizada para um agente de AI.
 */
export interface AiToolDefinition {
  /** Nome único da tool. */
  name: string;
  /** Descrição opcional usada para orientar o modelo. */
  description?: string;
  /** Schema JSON dos parâmetros aceitos pela tool. */
  parameters?: Record<string, unknown>;
}
