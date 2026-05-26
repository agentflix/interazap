import { Global, Module } from '@nestjs/common';
import { BusinessEventLogger } from '../common/logger/business-event.logger';
import { IdempotentWebhookGuard } from './guards';
import { IdempotentResponseInterceptor } from './interceptors';
import { IdempotencyModule } from './services/idempotency';
import { GatewayConfigService } from './services/gateway-config.service';

/**
 * Módulo compartilhado que disponibiliza serviços transversais a todos os módulos do gateway.
 *
 * Exporta logging de eventos de negócio, guards e interceptors de idempotência,
 * e o serviço de configuração do gateway.
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
