import { Global, Module } from '@nestjs/common';
import { GatewayCacheService } from './gateway-cache.service';

/**
 * Módulo global que disponibiliza o serviço de cache em duas camadas (L1 LRU + L2 Redis).
 *
 * Contexto: módulo infra/cache. `@Global()` elimina a necessidade de importar
 * em cada módulo de domínio — basta injetar GatewayCacheService diretamente.
 */
@Global()
@Module({
  providers: [GatewayCacheService],
  exports: [GatewayCacheService],
})
export class GatewayCacheModule {}
