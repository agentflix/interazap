import { Global, Module } from '@nestjs/common';
import { InternalApiKeyGuard } from '../domains/realtime/guards/internal-api-key.guard';
import { MetricsController } from './metrics.controller';
import { MetricsService } from './metrics.service';
import { BillingUsageMetrics } from './billing-usage.metrics';

/**
 * MetricsModule
 *
 * Global NestJS module that bootstraps the Prometheus metrics subsystem.
 * Registers MetricsController and MetricsService, both exported for use
 * across the gateway application.
 */
@Global()
@Module({
  controllers: [MetricsController],
  providers: [MetricsService, BillingUsageMetrics, InternalApiKeyGuard],
  exports: [MetricsService, BillingUsageMetrics],
})
export class MetricsModule {}
