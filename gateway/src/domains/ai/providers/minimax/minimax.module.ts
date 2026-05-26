/**
 * Módulo NestJS que configura e exporta o provider MiniMax.
 *
 * Contexto: registra `MiniMaxConfigService`, `MiniMaxTranslator`, `MiniMaxProviderAdapter`
 * e `CircuitBreakerService` como providers, exportando o config e o adapter para uso no `AIModule`.
 */

import { Module } from '@nestjs/common';
import { MiniMaxConfigService } from './minimax.config';
import { MiniMaxTranslator } from './minimax.translator';
import { MiniMaxProviderAdapter } from './minimax-provider.adapter';
import { CircuitBreakerService } from '../../../../shared/services/circuit-breaker/circuit-breaker.service';

@Module({
  providers: [
    MiniMaxConfigService,
    MiniMaxTranslator,
    MiniMaxProviderAdapter,
    CircuitBreakerService,
  ],
  exports: [MiniMaxConfigService, MiniMaxProviderAdapter],
})
export class MiniMaxProviderModule {}
