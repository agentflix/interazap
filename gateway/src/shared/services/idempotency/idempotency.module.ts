import { Global, Module } from '@nestjs/common';
import { IdempotencyService } from './idempotency.service';

/**
 * Idempotency Module.
 *
 * Provides the IdempotencyService globally to prevent
 * duplicate processing of webhooks and API requests.
 *
 * Usage:
 * 1. Import IdempotencyModule in AppModule
 * 2. Inject IdempotencyService in your services
 * 3. Use idempotency.execute() for protected operations
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
 *       // Process webhook - will only run once per event.id
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
