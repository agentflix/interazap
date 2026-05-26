import { Module } from '@nestjs/common';
import { WebhookDispatcherService } from './outbound/webhook-dispatcher.service';

/**
 * Módulo do domínio de webhooks de saída.
 *
 * Registra e exporta o WebhookDispatcherService para uso em outros módulos
 * que precisam despachar eventos para sistemas externos via HTTP.
 */
@Module({
  providers: [WebhookDispatcherService],
  exports: [WebhookDispatcherService],
})
export class WebhooksModule {}
