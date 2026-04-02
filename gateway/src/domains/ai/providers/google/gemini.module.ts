/**
 * Gemini Module
 *
 * Módulo NestJS que configura e exporta o provider Google Gemini.
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
