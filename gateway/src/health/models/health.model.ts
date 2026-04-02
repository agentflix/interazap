/**
 * Modelos do domínio de health check do gateway.
 * Define interfaces utilizadas pelo controller e serviço de health check.
 */

/**
 * Status geral de saúde da aplicação gateway.
 */
export interface HealthStatus {
  /** Status consolidado: healthy, degraded ou unhealthy */
  status: 'healthy' | 'degraded' | 'unhealthy';
  /** Timestamp ISO 8601 da verificação */
  timestamp: string;
  /** Status individual de cada serviço dependente */
  services: {
    /** Status do Redis */
    redis: ServiceStatus;
    /** Status dos consumers de stream */
    consumers: ServiceStatus;
  };
}

/**
 * Status de saúde de um serviço individual.
 */
export interface ServiceStatus {
  /** Status do serviço: healthy ou unhealthy */
  status: 'healthy' | 'unhealthy';
  /** Latência medida em milissegundos */
  latency_ms?: number;
  /** Mensagem descritiva do status */
  message?: string;
  /** Detalhes adicionais do serviço */
  details?: Record<string, unknown>;
}
