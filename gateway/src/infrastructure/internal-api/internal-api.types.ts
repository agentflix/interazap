/**
 * Tipos de resposta e erro do cliente HTTP interno.
 *
 * Contexto: módulo infra/internal-api.
 */

/**
 * Resposta genérica bem-sucedida do InternalApiClientService.
 */
export interface InternalApiResponse<T> {
  /** Dados retornados pela api/ interna. */
  data: T;
  /** Código HTTP da resposta. */
  statusCode: number;
}

/**
 * Estrutura de erro retornada pelo InternalApiClientService após esgotar as tentativas.
 */
export interface InternalApiError {
  /** Mensagem descritiva do erro. */
  message: string;
  /** Código HTTP da última resposta recebida. */
  statusCode: number;
  /** Nome da operação que falhou (para logs e métricas). */
  operation: string;
  /** Número da tentativa em que o erro ocorreu. */
  attempt: number;
}
