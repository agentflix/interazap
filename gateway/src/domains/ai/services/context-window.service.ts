import { Injectable } from '@nestjs/common';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { InternalAiClientService } from './internal-ai-client.service';
import { AiMetricsService } from './ai-metrics.service';
import type { ContextSnapshot } from '../models/context-window.model';
import { isRecent } from '../../../shared/utils/snapshot.util';

/**
 * Serviço responsável por resolver e armazenar em cache o contexto operacional de uma run.
 */
@Injectable()
export class ContextWindowService {
  constructor(
    private readonly redisService: RedisService,
    private readonly aiMetrics: AiMetricsService,
    private readonly internalAiClient: InternalAiClientService,
  ) {}

  /**
   * Resolve o contexto associado a um ticket usando snapshot recente, cache Redis ou API interna.
   * @param ticketId - Identificador do ticket usado para buscar o contexto.
   * @param traceId - Identificador opcional de rastreamento distribuído.
   * @param snapshot - Snapshot previamente hidratado para reaproveitamento imediato.
   * @returns Contexto estruturado disponível para a run.
   */
  async resolveContext(
    ticketId: string,
    traceId?: string,
    snapshot?: ContextSnapshot,
  ): Promise<Record<string, unknown>> {
    if (snapshot && isRecent(snapshot.hydrated_at ?? snapshot.hydratedAt)) {
      const context = snapshot.context;
      if (context && typeof context === 'object' && !Array.isArray(context)) {
        this.aiMetrics.recordContextCacheHit(true);
        return context;
      }
    }

    const key = `autopilot:context:${ticketId}`;
    const cached = await this.redisService.get(key);

    if (cached) {
      this.aiMetrics.recordContextCacheHit(true);
      try {
        return JSON.parse(cached) as Record<string, unknown>;
      } catch {
        return {};
      }
    }

    this.aiMetrics.recordContextCacheHit(false);
    const context = await this.internalAiClient.fetchContext(ticketId, traceId);
    await this.redisService.set(key, JSON.stringify(context), 1800);

    return context;
  }
}
