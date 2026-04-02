import { Module } from '@nestjs/common';
import { HealthController } from './health.controller';
import { HealthService } from './health.service';
import { CircuitHealthController } from './circuit-health.controller';
import { QueueDashboardController } from './queue-dashboard.controller';
import { QueueModule } from '../shared/services/queue';
import { InternalApiKeyGuard } from '../domains/realtime/guards/internal-api-key.guard';

/**
 * Health module for the gateway.
 *
 * Exposes health check endpoints for Kubernetes probes (liveness, readiness)
 * and internal monitoring (deep check, circuit breakers, queue dashboard).
 */
@Module({
  imports: [QueueModule],
  controllers: [
    HealthController,
    CircuitHealthController,
    QueueDashboardController,
  ],
  providers: [HealthService, InternalApiKeyGuard],
  exports: [HealthService],
})
export class HealthModule {}
