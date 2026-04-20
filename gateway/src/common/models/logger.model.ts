/**
 * Modelos de logging do gateway.
 * Define interfaces utilizadas pelos serviços de logger estruturado e eventos de negócio.
 */

/**
 * Estrutura de uma entrada de log estruturado em formato JSON.
 * Utilizada para logging em ambientes de produção.
 */
export interface StructuredLogEntry {
  /** Timestamp ISO 8601 do log */
  timestamp: string;
  /** Nível do log (info, warn, error, debug, verbose, fatal) */
  level: string;
  /** Nome do serviço que emitiu o log */
  service: string;
  /** Mensagem do log */
  message: string;
  /** Contexto/módulo que gerou o log */
  context?: string;
  /** ID de rastreamento distribuído */
  traceId?: string;
  /** ID do span dentro do trace */
  spanId?: string;
  /** Stack trace (apenas para erros) */
  stack?: string;
  /** Campos adicionais de contexto */
  [key: string]: unknown;
}

/**
 * Contexto de um evento de negócio registrado no gateway.
 */
export interface BusinessEventContext {
  /** ID do tenant relacionado ao evento */
  tenantId?: string;
  /** ID do usuário relacionado ao evento */
  userId?: string;
  /** ID de rastreamento distribuído */
  traceId?: string;
  /** Campos adicionais de contexto */
  [key: string]: unknown;
}
