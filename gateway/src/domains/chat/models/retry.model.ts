/**
 * Modelos do dominio Chat/Retry do gateway.
 * Tipos de configuracao para politica de retentativa exponencial.
 */

/**
 * Configuracao da politica de retry com backoff exponencial.
 */
export interface RetryOptions {
  /** Numero maximo de retentativas apos a primeira execucao. */
  maxRetries: number;
  /** Atraso base em milissegundos para o primeiro retry. */
  baseDelayMs: number;
  /** Limite maximo de atraso entre tentativas. */
  maxDelayMs: number;
  /** Base exponencial usada no calculo do backoff. */
  exponentialBase: number;
}

/**
 * Resultado consolidado de uma execucao com retentativas.
 */
export interface RetryResult<T> {
  /** Indica se a operacao foi concluida com sucesso. */
  success: boolean;
  /** Dado retornado pela operacao quando bem-sucedida. */
  data?: T;
  /** Numero de tentativas realizadas. */
  attempts: number;
  /** Ultimo erro registrado quando a operacao falhou. */
  lastError?: Error;
}
