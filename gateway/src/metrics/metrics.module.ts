import { Global, Module } from '@nestjs/common';
import { InternalApiKeyGuard } from '../domains/realtime/guards/internal-api-key.guard';
import { MetricsController } from './metrics.controller';
import { MetricsService } from './metrics.service';
import { BillingUsageMetrics } from './billing-usage.metrics';
import { BillingTrialMetrics } from './billing-trial.metrics';

/**
 * Módulo global que inicializa o subsistema de métricas Prometheus do gateway.
 *
 * Contexto: módulo metrics. Registra MetricsController (scraping endpoint),
 * MetricsService (registry centralizado) e as métricas de domínio
 * BillingUsageMetrics e BillingTrialMetrics.
 * `@Global()` disponibiliza MetricsService para injeção em qualquer módulo.
 */
@Global()
@Module({
  controllers: [MetricsController],
  providers: [MetricsService, BillingUsageMetrics, BillingTrialMetrics, InternalApiKeyGuard],
  exports: [MetricsService, BillingUsageMetrics, BillingTrialMetrics],
})
export class MetricsModule {}
