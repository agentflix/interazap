import { Injectable } from '@nestjs/common';
import { Counter, Histogram } from 'prom-client';
import { MetricsService } from './metrics.service';

/**
 * BillingUsageMetrics
 *
 * Métricas Prometheus específicas para tracking de uso de mensagens IA
 * e verificação de quotas de billing.
 */
@Injectable()
export class BillingUsageMetrics {
  public readonly aiMessagesTotal: Counter<'tenant_id' | 'mode' | 'allowed'>;
  public readonly usageCheckFailuresTotal: Counter<'reason'>;
  public readonly usageCheckDurationSeconds: Histogram<'tenant_id'>;

  constructor(private readonly metricsService: MetricsService) {
    const registry = this.metricsService.getRegistry();

    this.aiMessagesTotal = new Counter({
      name: 'ai_messages_total',
      help: 'Total AI messages processed grouped by tenant, mode and allowed status',
      labelNames: ['tenant_id', 'mode', 'allowed'],
      registers: [registry],
    });

    this.usageCheckFailuresTotal = new Counter({
      name: 'usage_check_failures_total',
      help: 'Total usage check failures grouped by reason',
      labelNames: ['reason'],
      registers: [registry],
    });

    this.usageCheckDurationSeconds = new Histogram({
      name: 'usage_check_duration_seconds',
      help: 'Duration of usage check API calls in seconds',
      labelNames: ['tenant_id'],
      buckets: [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5],
      registers: [registry],
    });
  }

  /**
   * Registra uma mensagem IA processada.
   *
   * @param tenantId - UUID do tenant
   * @param mode     - Modo de billing (stop, overage, fail_open)
   * @param allowed  - Se a mensagem foi permitida
   */
  recordAiMessage(tenantId: string, mode: string, allowed: boolean): void {
    this.aiMessagesTotal.inc({
      tenant_id: tenantId,
      mode,
      allowed: allowed ? 'true' : 'false',
    });
  }

  /**
   * Registra uma falha no check de usage.
   *
   * @param reason - Razão da falha (timeout, network_error, http_error)
   */
  recordUsageCheckFailure(reason: string): void {
    this.usageCheckFailuresTotal.inc({ reason });
  }

  /**
   * Registra a duração de um check de usage.
   *
   * @param tenantId    - UUID do tenant
   * @param durationMs  - Duração em milissegundos
   */
  recordUsageCheckDuration(tenantId: string, durationMs: number): void {
    this.usageCheckDurationSeconds.observe(
      { tenant_id: tenantId },
      durationMs / 1000,
    );
  }
}
