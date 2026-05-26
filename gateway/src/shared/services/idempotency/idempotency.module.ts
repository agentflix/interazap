import { Global, Module } from '@nestjs/common';
import { IdempotencyService } from './idempotency.service';

/**
 * Módulo global de idempotência do gateway.
 *
 * Disponibiliza o IdempotencyService globalmente para prevenir
 * o processamento duplicado de webhooks e requisições de API.
 *
 * Uso:
 * 1. Importar IdempotencyModule no AppModule
 * 2. Injetar IdempotencyService nos serviços
 * 3. Usar idempotency.execute() para operações protegidas
 *
 * @example
 * ```typescript
 * @Injectable()
 * class WebhookService {
 *   constructor(private idempotency: IdempotencyService) {}
 *
 *   async processWebhook(event: WebhookEvent): Promise<void> {
 *     const key = IdempotencyService.webhookKey('provider', event.id);
 *
 *     await this.idempotency.execute(key, async () => {
 *       // Processado apenas uma vez por event.id
 *       return this.handleEvent(event);
 *     });
 *   }
 * }
 * ```
 */
@Global()
@Module({
  providers: [IdempotencyService],
  exports: [IdempotencyService],
})
export class IdempotencyModule {}
