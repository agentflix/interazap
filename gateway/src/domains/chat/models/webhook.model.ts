/**
 * Modelos do dominio Chat/Webhook Controller do gateway.
 * Tipos de resultado de ACK do fluxo de recebimento de webhook.
 */

/**
 * Resultado do fluxo de ACK do webhook antes do processamento assincrono.
 */
export type AckOutcome =
  | 'first_seen'
  | 'duplicate'
  | 'connection_bypass'
  | 'accepted_unkeyed'
  | 'resolve_failed'
  | 'idempotency_error';
