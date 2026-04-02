import { Global, Module } from '@nestjs/common';
import { BusinessEventLogger } from '../common/logger/business-event.logger';
import { IdempotentWebhookGuard } from './guards';
import { IdempotentResponseInterceptor } from './interceptors';
import { IdempotencyModule } from './services/idempotency';
import { GatewayConfigService } from './services/gateway-config.service';

/**
 * Shared module providing cross-cutting services to all gateway modules.
 *
 * Exports business event logging, idempotency guards and interceptors,
 * and the gateway configuration service.
 */
@Global()
@Module({
  imports: [IdempotencyModule],
  providers: [
    BusinessEventLogger,
    IdempotentWebhookGuard,
    IdempotentResponseInterceptor,
    GatewayConfigService,
  ],
  exports: [
    BusinessEventLogger,
    IdempotentWebhookGuard,
    IdempotentResponseInterceptor,
    IdempotencyModule,
    GatewayConfigService,
  ],
})
export class SharedModule {}
