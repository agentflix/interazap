import { Global, Module } from '@nestjs/common';
import { GatewayCacheService } from './gateway-cache.service';

/**
 * Módulo global que disponibiliza GatewayCacheService (L1 LRU + L2 Redis).
 * @Global() elimina a necessidade de importar em cada módulo de domínio.
 */
@Global()
@Module({
  providers: [GatewayCacheService],
  exports: [GatewayCacheService],
})
export class GatewayCacheModule {}
