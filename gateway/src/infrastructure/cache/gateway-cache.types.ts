/**
 * Estratégia de cache com TTLs independentes para L1 (memória LRU) e L2 (Redis).
 *
 * Contexto: módulo infra/cache. Utilizada em `GatewayCacheService.getOrFetch`.
 */
export interface CacheStrategy {
  /** Tempo de vida em milissegundos para a camada L1 (LRU em memória). */
  l1TtlMs: number;
  /** Tempo de vida em milissegundos para a camada L2 (Redis). */
  l2TtlMs: number;
}

/**
 * Estratégias predefinidas de cache para os diferentes perfis de dado do gateway.
 *
 * Contexto: módulo infra/cache.
 */
export const CacheStrategies = {
  /** Dados quentes de curta vida — ex: resolução de instâncias ativas. */
  HOT: { l1TtlMs: 30_000, l2TtlMs: 120_000 } satisfies CacheStrategy,
  /** Dados de longa vida — ex: instância por ID. */
  WARM: { l1TtlMs: 60_000, l2TtlMs: 300_000 } satisfies CacheStrategy,
  /** Controle de acesso WebSocket — TTL curto por requisitos de segurança. */
  REALTIME: { l1TtlMs: 10_000, l2TtlMs: 60_000 } satisfies CacheStrategy,
} as const;

/**
 * Envelope de entrada de cache com metadados de quando foi armazenado.
 *
 * Contexto: módulo infra/cache.
 */
export interface CacheEntry<T> {
  /** Valor armazenado em cache. */
  value: T;
  /** Timestamp Unix em milissegundos de quando o valor foi inserido no cache. */
  cachedAt: number;
}
