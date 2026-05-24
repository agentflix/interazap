/** Estratégia de cache com TTLs para L1 (memória) e L2 (Redis). */
export interface CacheStrategy {
  l1TtlMs: number;
  l2TtlMs: number;
}

/** Estratégias predefinidas de cache. */
export const CacheStrategies = {
  /** Dados quentes de curta vida (ex: instance-resolver). */
  HOT: { l1TtlMs: 30_000, l2TtlMs: 120_000 } satisfies CacheStrategy,
  /** Dados de longa vida (ex: instance por id). */
  WARM: { l1TtlMs: 60_000, l2TtlMs: 300_000 } satisfies CacheStrategy,
  /** Controle de acesso ws (TTL curto por segurança). */
  REALTIME: { l1TtlMs: 10_000, l2TtlMs: 60_000 } satisfies CacheStrategy,
} as const;

export interface CacheEntry<T> {
  value: T;
  cachedAt: number;
}
