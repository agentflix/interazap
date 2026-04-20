import {
  Injectable,
  NestInterceptor,
  ExecutionContext,
  CallHandler,
  Logger,
} from '@nestjs/common';
import { Observable } from 'rxjs';
import { tap } from 'rxjs/operators';
import { randomUUID } from 'node:crypto';
import { Request, Response } from 'express';
import { StructuredLoggerService } from '../logger/structured-logger.service';

export const TRACE_ID_HEADER = 'X-Trace-ID';

/**
 * Interceptor for trace ID propagation via AsyncLocalStorage.
 *
 * - Generates UUID if X-Trace-ID not present in request
 * - Wraps the handler execution inside StructuredLoggerService.runWithTrace
 *   so all downstream logs automatically include traceId and spanId
 * - Propagates trace ID in response headers
 */
@Injectable()
export class TraceIdInterceptor implements NestInterceptor {
  private readonly logger = new Logger(TraceIdInterceptor.name);

  intercept(context: ExecutionContext, next: CallHandler): Observable<unknown> {
    const ctx = context.switchToHttp();
    const request = ctx.getRequest<Request>();
    const response = ctx.getResponse<Response>();

    // Get or generate trace ID
    const traceId =
      (request.headers[TRACE_ID_HEADER.toLowerCase()] as string) ||
      randomUUID();
    const spanId = randomUUID().slice(0, 8);

    // Store in request for legacy access
    request['traceId'] = traceId;

    // Add to response headers
    response.setHeader(TRACE_ID_HEADER, traceId);

    const startTime = Date.now();
    const { method, url } = request;

    // Wrap entire handler chain inside AsyncLocalStorage context
    return new Observable((subscriber) => {
      StructuredLoggerService.runWithTrace(traceId, spanId, () => {
        next
          .handle()
          .pipe(
            tap({
              next: () => {
                const duration = Date.now() - startTime;
                this.logger.log({
                  message: 'Request completed',
                  traceId,
                  spanId,
                  method,
                  url,
                  statusCode: response.statusCode,
                  duration_ms: duration,
                });
              },
              error: (error: Error) => {
                const duration = Date.now() - startTime;
                this.logger.error({
                  message: 'Request failed',
                  traceId,
                  spanId,
                  method,
                  url,
                  error: error?.message ?? 'Unknown error',
                  duration_ms: duration,
                });
              },
            }),
          )
          .subscribe(subscriber);
      });
    });
  }
}

/**
 * Helper to get trace ID from request.
 */
export function getTraceId(request: Request): string | undefined {
  return request['traceId'] as string | undefined;
}
