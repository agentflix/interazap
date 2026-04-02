import {
  Injectable,
  NestInterceptor,
  ExecutionContext,
  CallHandler,
  Logger,
} from '@nestjs/common';
import { Observable, tap } from 'rxjs';
import { Request } from 'express';
import { IdempotencyService } from '../services/idempotency';

/**
 * Extended request type with idempotency properties.
 */
interface IdempotentRequest extends Request {
  idempotencyKey?: string;
  idempotencyTtl?: number;
}

/**
 * Interceptor that marks webhook as processed after successful execution.
 *
 * Works in conjunction with IdempotentWebhookGuard.
 * The guard checks for duplicates and stores the key in the request,
 * this interceptor marks it as processed after the handler executes.
 *
 * @example
 * ```typescript
 * @UseGuards(IdempotentWebhookGuard)
 * @UseInterceptors(IdempotentResponseInterceptor)
 * @Post('webhook')
 * @Idempotent({ prefix: 'billing' })
 * async handleWebhook(@Body() data: WebhookDto) {
 *   return { success: true };
 * }
 * ```
 */
@Injectable()
export class IdempotentResponseInterceptor implements NestInterceptor {
  private readonly logger = new Logger(IdempotentResponseInterceptor.name);

  /**
   * Initialises the interceptor with the IdempotencyService used to persist results.
   *
   * @param idempotencyService - Service that stores processed webhook results in Redis
   */
  constructor(private readonly idempotencyService: IdempotencyService) {}

  /**
   * Intercepts successful handler responses and marks the idempotency key as processed.
   *
   * After the route handler succeeds, stores the response body in Redis under the
   * idempotency key so future duplicate requests receive the cached result.
   * On handler error the key is intentionally left un-set so retries are not blocked.
   *
   * @param context - NestJS execution context (used to read idempotency key from request)
   * @param next - Observable chain representing the route handler
   * @returns The observable passed through unchanged; marking is fire-and-forget
   */
  intercept(context: ExecutionContext, next: CallHandler): Observable<unknown> {
    const request = context.switchToHttp().getRequest<IdempotentRequest>();
    const idempotencyKey = request.idempotencyKey;
    const ttl = request.idempotencyTtl;

    if (!idempotencyKey) {
      return next.handle();
    }

    return next.handle().pipe(
      tap({
        next: (response: unknown) => {
          void this.idempotencyService
            .markProcessed(idempotencyKey, response, { ttlSeconds: ttl })
            .then(() => {
              this.logger.debug(`Marked as processed: ${idempotencyKey}`);
            })
            .catch((error: Error) => {
              this.logger.error(
                `Failed to mark as processed: ${error.message}`,
              );
            });
        },
        error: (error: Error) => {
          // Don't mark as processed on error
          this.logger.warn(
            `Webhook failed, not marking as processed: ${idempotencyKey}`,
            error.message,
          );
        },
      }),
    );
  }
}
