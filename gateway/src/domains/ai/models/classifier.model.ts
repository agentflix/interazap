/**
 * Modelos do domínio AI do gateway.
 * Centraliza contratos usados pelo consumidor de classificação de mensagens.
 */

/**
 * Decisões possíveis retornadas pelo classificador de mensagens.
 */
export type ClassifierDecision = 'RESPOND' | 'SKIP' | 'DEBOUNCE' | 'HUMAN_ONLY';

/**
 * Representa o payload mínimo aceito pelo classificador assíncrono.
 */
export interface ClassifierRequestPayload {
  /** Identificador do tenant associado à mensagem. */
  tenant_id?: string;
  /** Identificador do ticket relacionado. */
  ticket_id?: string;
  /** Identificador da run atual, quando já existir. */
  run_id?: string;
  /** Texto de entrada preferencial para classificação. */
  input_text?: string;
  /** Corpo alternativo da mensagem caso input_text não esteja preenchido. */
  body?: string;
  /** Campos adicionais encaminhados para processamento posterior. */
  [key: string]: unknown;
}
