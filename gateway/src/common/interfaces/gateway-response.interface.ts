/**
 * Interface de resposta do Gateway.
 *
 * Envelope padrão para respostas enviadas do Gateway ao Laravel via Redis Streams.
 * Garante consistência no formato tanto para cenários de sucesso quanto de erro.
 */

/**
 * Representa os códigos de erro padronizados retornados pelo Gateway para tratamento programático.
 */
export type GatewayErrorCode =
  // Provider errors
  | 'PROVIDER_TIMEOUT'
  | 'PROVIDER_RATE_LIMIT'
  | 'PROVIDER_AUTH_ERROR'
  | 'PROVIDER_SERVER_ERROR'
  | 'PROVIDER_CONTENT_FILTER'
  | 'PROVIDER_UNAVAILABLE'
  // Request errors
  | 'INVALID_REQUEST'
  | 'INVALID_PAYLOAD'
  | 'MISSING_REQUIRED_FIELD'
  // Gateway errors
  | 'UNKNOWN_PROVIDER'
  | 'UNKNOWN_ACTION'
  | 'INTERNAL_ERROR'
  | 'CIRCUIT_BREAKER_OPEN';

/**
 * Estrutura de erro padronizada
 */
export interface GatewayError {
  /**
   * Código de erro padronizado para programmatic handling
   */
  code: GatewayErrorCode;

  /**
   * Mensagem human-readable do erro
   */
  message: string;

  /**
   * Se o erro é transiente e pode ser retentado
   */
  retryable: boolean;

  /**
   * Detalhes adicionais do erro (stack trace em dev, etc.)
   */
  details?: unknown;
}

/**
 * Envelope padrão para respostas do Gateway
 *
 * @template T - Tipo do data retornado em caso de sucesso
 */
export interface GatewayResponse<T = unknown> {
  /**
   * UUID que correlaciona com o request original
   */
  correlationId: string;

  /**
   * ISO 8601 timestamp de quando a resposta foi gerada
   */
  timestamp: string;

  /**
   * Se a operação foi bem sucedida
   */
  success: boolean;

  /**
   * Dados da resposta (presente apenas se success = true)
   */
  data?: T;

  /**
   * Erro detalhado (presente apenas se success = false)
   */
  error?: GatewayError;

  /**
   * Tempo de processamento em milliseconds
   */
  processingTimeMs?: number;
}

/**
 * Cria um envelope GatewayResponse de sucesso com os dados fornecidos.
 *
 * @param correlationId - UUID que correlaciona com o request original
 * @param data - Dados de retorno da operação
 * @param processingTimeMs - Tempo de processamento em milissegundos (opcional)
 * @returns Envelope de resposta de sucesso
 */
export function createSuccessResponse<T>(
  correlationId: string,
  data: T,
  processingTimeMs?: number,
): GatewayResponse<T> {
  return {
    correlationId,
    timestamp: new Date().toISOString(),
    success: true,
    data,
    processingTimeMs,
  };
}

/**
 * Cria um envelope GatewayResponse de erro com as informações do erro fornecidas.
 *
 * @param correlationId - UUID que correlaciona com o request original
 * @param error - Objeto de erro padronizado do Gateway
 * @param processingTimeMs - Tempo de processamento em milissegundos (opcional)
 * @returns Envelope de resposta de erro
 */
export function createErrorResponse(
  correlationId: string,
  error: GatewayError,
  processingTimeMs?: number,
): GatewayResponse<never> {
  return {
    correlationId,
    timestamp: new Date().toISOString(),
    success: false,
    error,
    processingTimeMs,
  };
}

/**
 * Mapa de códigos de erro para a flag de retentativa.
 * Indica quais erros são transientes e podem ser retentados automaticamente.
 */
export const ERROR_RETRYABLE_MAP: Record<GatewayErrorCode, boolean> = {
  PROVIDER_TIMEOUT: true,
  PROVIDER_RATE_LIMIT: true,
  PROVIDER_AUTH_ERROR: false,
  PROVIDER_SERVER_ERROR: true,
  PROVIDER_CONTENT_FILTER: false,
  PROVIDER_UNAVAILABLE: true,
  INVALID_REQUEST: false,
  INVALID_PAYLOAD: false,
  MISSING_REQUIRED_FIELD: false,
  UNKNOWN_PROVIDER: false,
  UNKNOWN_ACTION: false,
  INTERNAL_ERROR: false,
  CIRCUIT_BREAKER_OPEN: true,
};

/**
 * Cria um objeto GatewayError preenchendo automaticamente o campo `retryable` via ERROR_RETRYABLE_MAP.
 *
 * @param code - Código de erro padronizado do Gateway
 * @param message - Mensagem legível do erro
 * @param details - Detalhes adicionais do erro (opcional)
 * @returns Objeto GatewayError com retryable resolvido
 */
export function createGatewayError(
  code: GatewayErrorCode,
  message: string,
  details?: unknown,
): GatewayError {
  return {
    code,
    message,
    retryable: ERROR_RETRYABLE_MAP[code],
    details,
  };
}
