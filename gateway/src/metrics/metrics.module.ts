import { Global, Module } from '@nestjs/common';
import { MetricsController } from './metrics.controller';
import { MetricsService } from './metrics.service';

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
  providers: [MetricsService],
  exports: [MetricsService],
})
export class MetricsModule {}
