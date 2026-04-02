/**
 * Modelos de configuração do Sentry para o gateway.
 * Define a interface de configuração utilizada pela integração com o Sentry.
 */

/**
 * Configuração do Sentry para monitoramento de erros e rastreamento de performance.
 */
export interface SentryConfig {
  /** DSN de conexão com o Sentry. Undefined desativa o Sentry. */
  dsn: string | undefined;
  /** Ambiente de execução (production, staging, development) */
  environment: string;
  /** Versão da release para rastreamento de erros */
  release: string;
  /** Taxa de amostragem de traces de performance (0.0 a 1.0) */
  tracesSampleRate: number;
  /** Taxa de amostragem de profiles (0.0 a 1.0) */
  profilesSampleRate: number;
  /** Se o modo debug do Sentry está habilitado */
  debug: boolean;
}
