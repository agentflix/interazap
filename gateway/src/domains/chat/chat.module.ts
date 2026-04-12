import { Module } from '@nestjs/common';
import { ChatWebhookController } from './controllers/chat-webhook.controller';
import { ChatWebhookService } from './services/chat-webhook.service';
import { ChatWebhookEventNormalizer } from './services/chat-webhook-event-normalizer.service';
import { ChatWebhookRealtimeProcessor } from './services/chat-webhook-realtime-processor.service';
import { UazapiProvider } from './providers/uazapi/uazapi.provider';
import { UazapiClient } from './providers/uazapi/uazapi.client';
import { UazapiAdapter } from './providers/uazapi/uazapi.adapter';
import { UazapiInstancesController } from './controllers/uazapi-instances.controller';
import { ChatController } from './controllers/chat.controller';
import {
  UazapiMessagesController,
  UazapiPresenceController,
} from './controllers/uazapi-messages.controller';
import { UazapiContactsController } from './controllers/uazapi-contacts.controller';
import { ZapiInstancesController } from './controllers/zapi-instances.controller';
import { ChatOutboundController } from './controllers/chat-outbound.controller';
import { SendMessageController } from './outbound/send-message.controller';
import { InstanceResolverService } from './services/instance-resolver.service';
import { ChatWebhookFileLoggerService } from './services/chat-webhook-file-logger.service';
import { WebhookIdempotencyService } from './services/webhook-idempotency.service';
import { PayloadSemanticsResolver } from './services/payload-semantics-resolver.service';
import { WebhookRealtimeEmitter } from './services/webhook-realtime-emitter.service';
import { TicketResolverService } from './services/ticket-resolver.service';
import { ConnectionStatusService } from './services/connection-status.service';
import { RealtimeModule } from '../realtime/realtime.module';
import { InternalApiKeyGuard } from '../realtime/guards/internal-api-key.guard';
import { WebhookNormalizationInterceptor } from './interceptors/webhook-normalization.interceptor';
import { RedisModule } from '../../infrastructure/redis/redis.module';
import { MetricsModule } from '../../metrics/metrics.module';

// Z-API Provider
import { ZapiClient } from './providers/zapi/zapi.client';
import { ZapiNormalizer } from './providers/zapi/zapi.normalizer';
import { ZapiAdapter } from './providers/zapi/zapi.adapter';
import { ProviderFactory } from './providers/provider.factory';

// Meta Provider
import { MetaModule } from './providers/meta/meta.module';
import { MetaWebhookController } from './controllers/meta-webhook.controller';
import { ChannelsController } from './channels.controller';

// Outbound
import { SendMessageService } from './outbound/send-message.service';
import { SendMessageConsumer } from './outbound/send-message.consumer';
import { RetryPolicy } from './outbound/retry-policy';

@Module({
  imports: [RealtimeModule, RedisModule, MetricsModule, MetaModule],
  controllers: [
    ChatWebhookController,
    UazapiInstancesController,
    UazapiMessagesController,
    UazapiPresenceController,
    UazapiContactsController,
    ZapiInstancesController,
    ChatOutboundController,
    SendMessageController,
    ChatController,
    MetaWebhookController,
    ChannelsController,
  ],
  providers: [
    InternalApiKeyGuard,
    ChatWebhookService,
    ChatWebhookEventNormalizer,
    ChatWebhookRealtimeProcessor,
    UazapiProvider,
    UazapiAdapter,
    UazapiClient,
    InstanceResolverService,
    ChatWebhookFileLoggerService,
    WebhookIdempotencyService,
    PayloadSemanticsResolver,
    WebhookRealtimeEmitter,
    TicketResolverService,
    ConnectionStatusService,
    WebhookNormalizationInterceptor,
    // Z-API
    ZapiClient,
    ZapiNormalizer,
    ZapiAdapter,
    ProviderFactory,
    // Outbound
    RetryPolicy,
    SendMessageService,
    SendMessageConsumer,
  ],
  exports: [ProviderFactory, SendMessageService],
})
export class ChatModule {}
