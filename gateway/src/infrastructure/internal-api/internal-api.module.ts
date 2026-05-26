import { Global, Module } from '@nestjs/common';
import { InternalApiClientService } from './internal-api-client.service';

/**
 * Módulo global que disponibiliza InternalApiClientService para toda a aplicação gateway.
 *
 * Contexto: módulo infra/internal-api. `@Global()` elimina a necessidade de importar
 * em cada módulo de domínio — basta injetar InternalApiClientService diretamente.
 */
@Global()
@Module({
  providers: [InternalApiClientService],
  exports: [InternalApiClientService],
})
export class InternalApiModule {}
