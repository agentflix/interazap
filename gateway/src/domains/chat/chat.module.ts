import { Module } from '@nestjs/common';
import { QueueModule } from '../../shared/services/queue/queue.module';
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

// Provider Z-API
import { ZapiClient } from './providers/zapi/zapi.client';
import { ZapiNormalizer } from './providers/zapi/zapi.normalizer';
import { ZapiAdapter } from './providers/zapi/zapi.adapter';
import { ProviderFactory } from './providers/provider.factory';

// Provider Meta
import { MetaModule } from './providers/meta/meta.module';
import { MetaWebhookController } from './controllers/meta-webhook.controller';
import { ChannelsController } from './channels.controller';
import { MetaWebhookQueueService } from './services/meta-webhook-queue.service';
import { MetaWebhookProcessor } from './processors/meta-webhook.processor';

// Outbound
import { SendMessageService } from './outbound/send-message.service';
import { SendMessageConsumer } from './outbound/send-message.consumer';
import { RetryPolicy } from './outbound/retry-policy';
import { UpdateConnectionStatusProcessor } from './processors/update-connection-status.processor';

/**
 * Modulo principal do dominio Chat no gateway.
 *
 * Contexto: agrega todos os controllers, servicos e providers de mensageria
 * (Uazapi, Z-API, Meta). Integra RealtimeModule para emissao de eventos WebSocket,
 * RedisModule para cache e idempotencia, MetricsModule para observabilidade
 * e QueueModule para consumo de streams Redis e filas BullMQ.
 */
@Module({
  imports: [
    RealtimeModule,
    RedisModule,
    MetricsModule,
    MetaModule,
    QueueModule,
  ],
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
    // Z-API Provider
    ZapiClient,
    ZapiNormalizer,
    ZapiAdapter,
    ProviderFactory,
    // Outbound
    RetryPolicy,
    SendMessageService,
    SendMessageConsumer,
    // Processadores
    UpdateConnectionStatusProcessor,
    MetaWebhookQueueService,
    MetaWebhookProcessor,
  ],
  exports: [ProviderFactory, SendMessageService],
})
export class ChatModule {}
