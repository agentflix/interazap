import { Module } from '@nestjs/common';
import { WebhookDispatcherService } from './outbound/webhook-dispatcher.service';

@Module({
  providers: [WebhookDispatcherService],
  exports: [WebhookDispatcherService],
})
export class WebhooksModule {}
