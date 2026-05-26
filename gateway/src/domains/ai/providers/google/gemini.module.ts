/**
 * Módulo NestJS que configura e exporta o provider Google Gemini.
 *
 * Contexto: registra `GeminiConfigService`, `GeminiTranslator`, `GeminiProviderAdapter`
 * e `CircuitBreakerService` como providers, exportando o config e o adapter para uso no `AIModule`.
 */

import { Module } from '@nestjs/common';
import { GeminiConfigService } from './gemini.config';
import { GeminiTranslator } from './gemini.translator';
import { GeminiProviderAdapter } from './gemini-provider.adapter';
import { CircuitBreakerService } from '../../../../shared/services/circuit-breaker/circuit-breaker.service';

@Module({
  providers: [
    GeminiConfigService,
    GeminiTranslator,
    GeminiProviderAdapter,
    CircuitBreakerService,
  ],
  exports: [GeminiConfigService, GeminiProviderAdapter],
})
export class GeminiProviderModule {}
