import { Injectable } from '@nestjs/common';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { InternalAiClientService } from './internal-ai-client.service';
import { AiMetricsService } from './ai-metrics.service';
import type { PromptSnapshot } from '../models/prompt.model';
import { isRecent } from '../../../shared/utils/snapshot.util';

/**
 * Serviço responsável por resolver e armazenar em cache o prompt base do tenant.
 */
@Injectable()
export class PromptAssemblerService {
  constructor(
    private readonly redisService: RedisService,
    private readonly aiMetrics: AiMetricsService,
    private readonly internalAiClient: InternalAiClientService,
  ) {}

  /**
   * Resolve o prompt base usando snapshot recente, cache Redis ou API interna.
   * @param tenantId - Identificador do tenant do qual o prompt será carregado.
   * @param traceId - Identificador opcional de rastreamento distribuído.
   * @param snapshot - Snapshot previamente hidratado do prompt.
   * @returns Prompt final a ser usado na execução da run.
   */
  async resolvePrompt(
    tenantId: string,
    traceId?: string,
    snapshot?: PromptSnapshot,
  ): Promise<string> {
    if (
      snapshot &&
      isRecent(snapshot.hydrated_at ?? snapshot.hydratedAt) &&
      typeof snapshot.prompt === 'string' &&
      snapshot.prompt.trim() !== ''
    ) {
      this.aiMetrics.recordPromptCacheHit(true);
      this.aiMetrics.recordSnapshotResolution('prompt', 'snapshot');
      return snapshot.prompt;
    }

    const key = `autopilot:prompt:${tenantId}`;
    const cached = await this.redisService.get(key);

    if (cached) {
      this.aiMetrics.recordPromptCacheHit(true);
      this.aiMetrics.recordSnapshotResolution('prompt', 'redis');
      return cached;
    }

    this.aiMetrics.recordPromptCacheHit(false);
    this.aiMetrics.recordSnapshotResolution('prompt', 'api');
    const prompt = await this.internalAiClient.fetchPrompt(tenantId, traceId);
    await this.redisService.set(key, prompt, 3600);

    return prompt;
  }
}
