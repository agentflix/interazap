import { Module } from '@nestjs/common';
import { BillingUsageClient } from './services/billing-usage-client.service';

/**
 * Módulo de clientes HTTP do domínio de billing.
 *
 * Agrupa e exporta o BillingUsageClient para uso em outros módulos
 * que precisam verificar e incrementar o consumo de mensagens IA.
 */
@Module({
  providers: [BillingUsageClient],
  exports: [BillingUsageClient],
})
export class BillingClientsModule {}
