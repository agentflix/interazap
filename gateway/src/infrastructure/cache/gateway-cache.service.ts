import { Injectable, Logger, OnModuleInit } from '@nestjs/common';
import { LRUCache } from 'lru-cache';
import { Counter } from 'prom-client';
import { RedisService } from '../redis/redis.service';
import { MetricsService } from '../../metrics/metrics.service';
import type { CacheStrategy } from './gateway-cache.types';

const msToSec = (ms: number): number => Math.max(1, Math.ceil(ms / 1000));

const INVALIDATION_CHANNEL = 'cache:invalidate:instance';

/**
 * Fachada de cache em duas camadas: L1 LRU em memória + L2 Redis.
 *
 * Contexto: módulo infra/cache. Abstrai a lógica de cache hierárquico para
 * os domínios do gateway, expondo um único método `getOrFetch`. A invalidação
 * entre instâncias é feita via pub/sub Redis no canal `cache:invalidate:instance`.
 * Registra contadores Prometheus `gateway_cache_hits_total` e `gateway_cache_misses_total`.
 */
@Injectable()
export class GatewayCacheService implements OnModuleInit {
  private readonly logger = new Logger(GatewayCacheService.name);

  private readonly l1 = new LRUCache<string, object>({
    max: 1_000,
    ttl: 60_000,
  });

  private hitsCounter!: Counter<'level' | 'operation'>;
  private missesCounter!: Counter<'operation'>;

  constructor(
    private readonly redisService: RedisService,
    private readonly metricsService: MetricsService,
  ) {}

  /** Registra os contadores Prometheus e inicia a subscrição de invalidação via pub/sub Redis. */
  onModuleInit(): void {
    const registry = this.metricsService.getRegistry();

    this.hitsCounter = new Counter({
      name: 'gateway_cache_hits_total',
      help: 'Total cache hits by level and operation',
      labelNames: ['level', 'operation'],
      registers: [registry],
    });

    this.missesCounter = new Counter({
      name: 'gateway_cache_misses_total',
      help: 'Total cache misses by operation',
      labelNames: ['operation'],
      registers: [registry],
    });

    this.subscribeInvalidation();
  }

  /**
   * Retorna o valor da chave a partir da camada mais próxima disponível.
   * Ordem: L1 (LRU memória) → L2 (Redis) → fetcher (origem).
   * Após miss total, popula as camadas ausentes com o valor obtido.
   * @param key Chave de cache
   * @param fetcher Função assíncrona que busca o valor na origem em caso de miss total
   * @param strategy Estratégia com TTLs para L1 e L2
   * @param operation Nome da operação para labels Prometheus
   * @returns Valor tipado da cache ou da origem
   */
  async getOrFetch<T extends object>(
    key: string,
    fetcher: () => Promise<T>,
    strategy: CacheStrategy,
    operation: string,
  ): Promise<T> {
    // L1 check
    const l1Value = this.l1.get(key);
    if (l1Value !== undefined) {
      this.hitsCounter.inc({ level: 'l1', operation });
      return l1Value as T;
    }

    // L2 check
    try {
      const l2Raw = await this.redisService.get(this.redisKey(key));

      if (l2Raw !== null) {
        const parsed = JSON.parse(l2Raw) as T;
        this.l1.set(key, parsed, { ttl: strategy.l1TtlMs });
        this.hitsCounter.inc({ level: 'l2', operation });
        return parsed;
      }
    } catch (err) {
      this.logger.warn(`[cache] L2 read error for key ${key}: ${String(err)}`);
    }

    // Miss total — executar fetcher
    this.missesCounter.inc({ operation });
    const value = await fetcher();

    // Populate L1
    this.l1.set(key, value, { ttl: strategy.l1TtlMs });

    // Populate L2
    try {
      await this.redisService.set(
        this.redisKey(key),
        JSON.stringify(value),
        msToSec(strategy.l2TtlMs),
      );
    } catch (err) {
      this.logger.warn(`[cache] L2 write error for key ${key}: ${String(err)}`);
    }

    return value;
  }

  /**
   * Remove uma entrada das camadas L1 (LRU) e L2 (Redis).
   * @param key Chave de cache a ser invalidada
   */
  async invalidate(key: string): Promise<void> {
    this.l1.delete(key);
    try {
      await this.redisService.delete(this.redisKey(key));
    } catch (err) {
      this.logger.warn(
        `[cache] L2 delete error for key ${key}: ${String(err)}`,
      );
    }
  }

  /**
   * Subscreve ao canal Redis de invalidação para remover entradas L1 quando
   * outros nós publicarem uma chave para invalidar.
   */
  private subscribeInvalidation(): void {
    const subscriber = this.redisService.getPubSubClient();

    void subscriber.subscribe(INVALIDATION_CHANNEL, (err) => {
      if (err) {
        this.logger.error(
          `[cache] Failed to subscribe to ${INVALIDATION_CHANNEL}: ${String(err)}`,
        );
      }
    });

    subscriber.on('message', (channel: string, message: string) => {
      if (channel !== INVALIDATION_CHANNEL) return;

      this.logger.debug(`[cache] invalidate key: ${message}`);
      this.l1.delete(message);
      this.redisService
        .delete(this.redisKey(message))
        .catch((err: unknown) =>
          this.logger.warn(`[cache] async L2 invalidate error: ${String(err)}`),
        );
    });
  }

  /**
   * Prefixa a chave com o namespace do cache para evitar colisões no Redis.
   * @param key Chave lógica de cache
   * @returns Chave Redis no formato `gw:cache:{key}`
   */
  private redisKey(key: string): string {
    return `gw:cache:${key}`;
  }
}
