import { Module } from '@nestjs/common';
import { MetaClient } from './meta.client';
import { MetaProvider } from './meta.provider';
import { MetaAdapter } from './meta.adapter';
import { MetaConfigService } from './meta.config';
import { MetaLookupService } from '../../http/meta-lookup.service';

/**
 * MetaModule
 *
 * Modulo do provider Meta WhatsApp Business API.
 * Fornece cliente HTTP para Meta Graph API, normalizador de webhooks,
 * e adaptador implementando a interface MetaWhatsAppProvider.
 */
@Module({
  providers: [MetaClient, MetaProvider, MetaAdapter, MetaConfigService, MetaLookupService],
  exports: [MetaAdapter, MetaClient, MetaConfigService, MetaLookupService],
})
export class MetaModule {}
