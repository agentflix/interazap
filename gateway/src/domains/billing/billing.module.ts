import { Module } from '@nestjs/common';
import { BillingWebhookController } from './controllers/billing-webhook.controller';
import { BillingController } from './controllers/billing.controller';
import { BillingCollectionController } from './controllers/billing-collection.controller';
import { PlatformProductsController } from './controllers/platform-products.controller';
import { PaymentMethodController } from './controllers/payment-method.controller';
import { BillingWebhookService } from './services/billing-webhook.service';
import { BillingCollectionService } from './services/billing-collection.service';
import { BillingTenantResolverService } from './services/billing-tenant-resolver.service';
import { AsaasNormalizer } from './providers/asaas/asaas.normalizer';
import { AsaasClient } from './providers/asaas/asaas.client';
import { InternalApiKeyGuard } from '../realtime/guards/internal-api-key.guard';
import { ChatModule } from '../chat/chat.module';
import { QueueModule } from '../../shared/services/queue/queue.module';

/**
 * Módulo raiz do domínio de billing.
 *
 * Registra controllers para recebimento de webhooks, gerenciamento de cobranças,
 * envio de notificações de cobrança via WhatsApp e sincronização de produtos.
 * Exporta o AsaasNormalizer para uso em outros módulos que precisam normalizar
 * eventos de pagamento.
 */
@Module({
  imports: [ChatModule, QueueModule],
  controllers: [
    BillingWebhookController,
    BillingController,
    BillingCollectionController,
    PlatformProductsController,
    PaymentMethodController,
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
export class BillingModule {}
