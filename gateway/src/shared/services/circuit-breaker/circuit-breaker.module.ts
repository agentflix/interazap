import { Global, Module } from '@nestjs/common';
import { CircuitBreakerService } from './circuit-breaker.service';

/**
 * Módulo global de circuit breaker do gateway.
 *
 * Disponibiliza o CircuitBreakerService globalmente para proteger
 * chamadas a serviços externos de falhas em cascata.
 *
 * Uso:
 * 1. Importar CircuitBreakerModule no AppModule
 * 2. Injetar CircuitBreakerService nos serviços
 * 3. Usar circuitBreaker.call() ou o decorator @CircuitBreaker
 *
 * @example
 * ```typescript
 * // Em app.module.ts
 * @Module({
 *   imports: [CircuitBreakerModule],
 * })
 * export class AppModule {}
 *
 * // Em seu serviço
 * @Injectable()
 * class MyService {
 *   constructor(private circuitBreaker: CircuitBreakerService) {}
 *
 *   async callExternalApi(): Promise<Data> {
 *     return this.circuitBreaker.call('external-api', () =>
 *       this.httpClient.get('/api/data')
 *     );
 *   }
 * }
 * ```
 */
@Global()
@Module({
  providers: [CircuitBreakerService],
  exports: [CircuitBreakerService],
})
export class CircuitBreakerModule {}
