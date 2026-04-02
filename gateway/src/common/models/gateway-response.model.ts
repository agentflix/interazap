/**
 * Modelos canônicos de resposta do Gateway para integração via streams.
 */

/** Códigos de erro padronizados retornados pelo Gateway. */
export type GatewayErrorCode =
  | 'PROVIDER_TIMEOUT'
  | 'PROVIDER_RATE_LIMIT'
  | 'PROVIDER_AUTH_ERROR'
  | 'PROVIDER_SERVER_ERROR'
  | 'PROVIDER_CONTENT_FILTER'
  | 'PROVIDER_UNAVAILABLE'
  | 'INVALID_REQUEST'
  | 'INVALID_PAYLOAD'
  | 'MISSING_REQUIRED_FIELD'
  | 'UNKNOWN_PROVIDER'
  | 'UNKNOWN_ACTION'
  | 'INTERNAL_ERROR'
  | 'CIRCUIT_BREAKER_OPEN';

/** Estrutura padronizada de erro do Gateway. */
export interface GatewayError {
  code: GatewayErrorCode;
  message: string;
  retryable: boolean;
  details?: unknown;
}

/** Envelope padrão para retorno de operações executadas no Gateway. */
export interface GatewayResponse<T = unknown> {
  correlationId: string;
  timestamp: string;
  success: boolean;
  data?: T;
  error?: GatewayError;
  processingTimeMs?: number;
}
