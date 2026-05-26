import { Global, Module } from '@nestjs/common';
import { RedisService } from './redis.service';
import { RedisStreamsService } from './redis-streams.service';

/**
 * Módulo global que gerencia as conexões Redis do gateway.
 *
 * Contexto: módulo infra/redis. Fornece o RedisService (três clients dedicados:
 * comandos, pub/sub e bloqueante) e o RedisStreamsService para operações de stream.
 * `@Global()` elimina a necessidade de importar em cada módulo de domínio.
 */
@Global()
@Module({
  providers: [RedisService, RedisStreamsService],
  exports: [RedisService, RedisStreamsService],
})
export class RedisModule {}
