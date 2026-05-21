import { Injectable } from '@nestjs/common';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { AiMetricsService } from './ai-metrics.service';
import { GatewayConfigService } from '../../../shared/services/gateway-config.service';
import { AI_EVENTS, tenantRoom, runRoom } from '../../../shared/constants';

/**
 * Serviço responsável por publicar eventos de streaming e finalização de runs para WebSocket.
 */
@Injectable()
export class StreamHandlerService {
  constructor(
    private readonly redisService: RedisService,
    private readonly aiMetrics: AiMetricsService,
    private readonly gatewayConfigService: GatewayConfigService,
  ) {}

  /**
   * Emite um chunk parcial de resposta para os canais WebSocket da run.
   * @param tenantId - Identificador do tenant dono da execução.
   * @param runId - Identificador da run em andamento.
   * @param agentId - Identificador do agente que produziu o chunk.
   * @param chunk - Trecho textual a ser transmitido.
   * @param traceId - Identificador opcional de rastreamento distribuído.
   * @returns Promise resolvida após a publicação do evento.
   */
  async emitChunk(
    tenantId: string,
    runId: string,
    agentId: string,
    chunk: string,
    traceId?: string,
    correlationId?: string,
  ): Promise<void> {
    this.aiMetrics.recordStreamChunk(agentId);

    await this.redisService.publish(this.gatewayConfigService.wsEventsChannel, {
      event: AI_EVENTS.RUN_STREAMING,
      tenant_id: tenantId,
      correlation_id: correlationId,
      trace_id: traceId,
      rooms: [tenantRoom(tenantId), runRoom(runId)],
      data: {
        tenant_id: tenantId,
        run_id: runId,
        correlation_id: correlationId,
        trace_id: traceId,
        chunk,
        streaming: true,
      },
    });
  }

  /**
   * Emite o conteúdo final consolidado da run para os canais WebSocket.
   * @param tenantId - Identificador do tenant dono da execução.
   * @param runId - Identificador da run concluída.
   * @param content - Conteúdo final produzido para a run.
   * @param traceId - Identificador opcional de rastreamento distribuído.
   * @returns Promise resolvida após a publicação do evento final.
   */
  async emitFinal(
    tenantId: string,
    runId: string,
    content: string,
    traceId?: string,
    correlationId?: string,
  ): Promise<void> {
    await this.redisService.publish(this.gatewayConfigService.wsEventsChannel, {
      event: AI_EVENTS.RUN_COMPLETED,
      tenant_id: tenantId,
      correlation_id: correlationId,
      trace_id: traceId,
      rooms: [tenantRoom(tenantId), runRoom(runId)],
      data: {
        tenant_id: tenantId,
        run_id: runId,
        correlation_id: correlationId,
        trace_id: traceId,
        content,
      },
    });
  }
}
