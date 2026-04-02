import {
  CallHandler,
  ExecutionContext,
  Injectable,
  NestInterceptor,
} from '@nestjs/common';
import { Observable } from 'rxjs';
import { isRecord } from '../../../shared/utils/type-guards';

/**
 * Interceptor que preserva payload bruto e normaliza metadados basicos de webhook.
 */
@Injectable()
export class WebhookNormalizationInterceptor implements NestInterceptor {
  /**
   * Ajusta o corpo da requisicao antes de delegar para o proximo handler.
   */
  intercept(context: ExecutionContext, next: CallHandler): Observable<unknown> {
    const request = context.switchToHttp().getRequest<{ body?: unknown }>();
    const body = request.body;

    if (isRecord(body)) {
      const record = body;
      if (!('raw' in record)) {
        record.raw = { ...record } as Record<string, unknown>;
      }
      if (!('event_type' in record)) {
        const candidates = [
          record.EventType,
          record.eventType,
          record.event,
          record.type,
        ];
        const eventType = candidates.find(
          (candidate) => typeof candidate === 'string' && candidate.length > 0,
        );
        if (typeof eventType === 'string') {
          record.event_type = eventType;
        }
      }
    }

    return next.handle();
  }
}
