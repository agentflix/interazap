/**
 * Modelos do domínio AI do gateway.
 * Define os contratos de avaliação de guardrails do domínio.
 */

/**
 * Representa o resultado de uma avaliação de segurança ou conformidade.
 */
export interface GuardrailEvaluationResult {
  /** Indica se o conteúdo avaliado foi permitido. */
  allowed: boolean;
  /** Motivo opcional do bloqueio quando a avaliação falha. */
  reason?: string;
}
