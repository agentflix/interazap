import { Global, Module } from '@nestjs/common';
import { CircuitBreakerService } from './circuit-breaker.service';

/**
 * Circuit Breaker Module.
 *
 * Provides the CircuitBreakerService globally to protect
 * external service calls from cascading failures.
 *
 * Usage:
 * 1. Import CircuitBreakerModule in AppModule
 * 2. Inject CircuitBreakerService in your services
 * 3. Use circuitBreaker.call() or @CircuitBreaker decorator
 *
 * @example
 * ```typescript
 * // In app.module.ts
 * @Module({
 *   imports: [CircuitBreakerModule],
 * })
 * export class AppModule {}
 *
 * // In your service
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
