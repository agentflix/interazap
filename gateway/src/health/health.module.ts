import { Module } from '@nestjs/common';
import { HealthController } from './health.controller';
import { HealthService } from './health.service';
import { CircuitHealthController } from './circuit-health.controller';
import { QueueDashboardController } from './queue-dashboard.controller';
import { QueueModule } from '../shared/services/queue';
import { InternalApiKeyGuard } from '../domains/realtime/guards/internal-api-key.guard';

/**
 * Módulo de health check do gateway.
 *
 * Contexto: módulo health. Expõe endpoints para probes do Kubernetes
 * (liveness, readiness) e monitoramento interno (deep check,
 * circuit breakers e dashboard de filas).
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
