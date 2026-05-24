import { Global, Module } from '@nestjs/common';
import { InternalApiClientService } from './internal-api-client.service';

/**
 * Módulo global que disponibiliza InternalApiClientService para toda a aplicação.
 * @Global() elimina a necessidade de importar em cada módulo de domínio.
 */
@Global()
@Module({
  providers: [InternalApiClientService],
  exports: [InternalApiClientService],
})
export class InternalApiModule {}
