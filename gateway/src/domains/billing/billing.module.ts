import { Module } from '@nestjs/common';
import { BillingWebhookController } from './controllers/billing-webhook.controller';
import { BillingController } from './controllers/billing.controller';
import { BillingCollectionController } from './controllers/billing-collection.controller';
import { PlatformProductsController } from './controllers/platform-products.controller';
import { BillingWebhookService } from './services/billing-webhook.service';
import { BillingCollectionService } from './services/billing-collection.service';
import { BillingTenantResolverService } from './services/billing-tenant-resolver.service';
import { AsaasNormalizer } from './providers/asaas/asaas.normalizer';
import { AsaasClient } from './providers/asaas/asaas.client';
import { InternalApiKeyGuard } from '../realtime/guards/internal-api-key.guard';
import { ChatModule } from '../chat/chat.module';
import { QueueModule } from '../../shared/services/queue/queue.module';

@Module({
  imports: [ChatModule, QueueModule],
  controllers: [
    BillingWebhookController,
    BillingController,
    BillingCollectionController,
    PlatformProductsController,
  ],
  providers: [
    BillingWebhookService,
    BillingCollectionService,
    BillingTenantResolverService,
    AsaasNormalizer,
    AsaasClient,
    InternalApiKeyGuard,
  ],
  exports: [AsaasNormalizer],
})
/**
 * Root module for the Billing domain.
 *
 * Provides controllers for webhooks, billing management, collection notifications,
 * and platform product synchronization. Exports AsaasNormalizer for use in other modules.
 */
export class BillingModule {}
