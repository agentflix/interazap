/**
 * Módulo NestJS que configura e exporta o provider OpenAI.
 *
 * Contexto: registra `OpenAIConfigService`, `OpenAITranslator`, `OpenAIProviderAdapter`
 * e `CircuitBreakerService` como providers, exportando o config e o adapter para uso no `AIModule`.
 */

import { Module } from '@nestjs/common';
import { OpenAIConfigService } from './openai.config';
import { OpenAITranslator } from './openai.translator';
import { OpenAIProviderAdapter } from './openai-provider.adapter';
import { CircuitBreakerService } from '../../../../shared/services/circuit-breaker/circuit-breaker.service';

@Module({
  providers: [
    OpenAIConfigService,
    OpenAITranslator,
    OpenAIProviderAdapter,
    CircuitBreakerService,
  ],
  exports: [OpenAIConfigService, OpenAIProviderAdapter],
})
export class OpenAIProviderModule {}
