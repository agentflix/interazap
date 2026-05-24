import { Injectable, Logger, OnModuleInit } from '@nestjs/common';
import { LRUCache } from 'lru-cache';
import { Counter } from 'prom-client';
import { RedisService } from '../redis/redis.service';
import { MetricsService } from '../../metrics/metrics.service';
import type { CacheStrategy } from './gateway-cache.types';

const msToSec = (ms: number): number => Math.max(1, Math.ceil(ms / 1000));

const INVALIDATION_CHANNEL = 'cache:invalidate:instance';

/**
 * Facade de cache em duas camadas: L1 LRU em memória + L2 Redis.
 *
 * getOrFetch<T>(key, fetcher, strategy): verifica L1 → L2 → fetcher, popula ambas.
 * Invalidação via pub/sub Redis no canal 'cache:invalidate:instance'.
 * Contadores Prometheus gateway_cache_hits_total e gateway_cache_misses_total.
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
   * Verifica L1, depois L2, executa fetcher se miss total.
   * Popula as camadas não preenchidas.
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

  /** Remove uma entrada de L1 e L2. */
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

  private redisKey(key: string): string {
    return `gw:cache:${key}`;
  }
}
