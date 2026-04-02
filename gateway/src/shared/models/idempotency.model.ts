/**
 * Modelos de idempotência do gateway.
 * Define interfaces utilizadas pelo serviço de idempotência e guard de webhooks.
 */

/**
 * Opções de configuração para verificações de idempotência.
 */
export interface IdempotencyOptions {
  /** TTL em segundos para chaves de idempotência. Padrão: 24 horas */
  ttlSeconds?: number;
  /** Prefixo para chaves de cache */
  prefix?: string;
}

/**
 * Opções para o decorator Idempotent aplicado em rotas de webhook.
 */
export interface IdempotentOptions {
  /** TTL em segundos para chaves de idempotência. Padrão: 24 horas */
  ttlSeconds?: number;
  /** Prefixo para chaves de cache. Padrão: 'webhook' */
  prefix?: string;
  /** Nome do header para a chave de idempotência. Padrão: 'x-idempotency-key' */
  headerName?: string;
  /** Função para extrair a chave da requisição. Opcional. */
  keyExtractor?: (req: import('express').Request) => string | null;
}
