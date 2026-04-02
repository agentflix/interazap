import {
  Injectable,
  NestInterceptor,
  ExecutionContext,
  CallHandler,
  Logger,
} from '@nestjs/common';
import { Observable, tap } from 'rxjs';
import { randomUUID } from 'node:crypto';
import { Request, Response } from 'express';

export const TRACE_ID_HEADER = 'X-Trace-ID';

/**
 * Interceptor for trace ID propagation.
 *
 * - Generates UUID if X-Trace-ID not present in request
 * - Adds trace ID to log context
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

    // Store in request for later access
    request['traceId'] = traceId;

    // Add to response headers
    response.setHeader(TRACE_ID_HEADER, traceId);

    const startTime = Date.now();
    const { method, url } = request;

    return next.handle().pipe(
      tap({
        next: () => {
          const duration = Date.now() - startTime;
          this.logger.log({
            message: 'Request completed',
            traceId,
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
            method,
            url,
            error: error?.message ?? 'Unknown error',
            duration_ms: duration,
          });
        },
      }),
    );
  }
}

/**
 * Helper to get trace ID from request.
 */
export function getTraceId(request: Request): string | undefined {
  return request['traceId'] as string | undefined;
}
