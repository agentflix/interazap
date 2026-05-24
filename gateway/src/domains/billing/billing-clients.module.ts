import { Module } from '@nestjs/common';
import { BillingUsageClient } from './services/billing-usage-client.service';

@Module({
  providers: [BillingUsageClient],
  exports: [BillingUsageClient],
})
export class BillingClientsModule {}
