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
 * Interceptor para propagação de trace ID via AsyncLocalStorage.
 *
 * Contexto: aplicado globalmente no gateway para rastreamento distribuído de requisições.
 * - Gera UUID se o header X-Trace-ID não estiver presente na requisição
 * - Envolve a execução do handler dentro de StructuredLoggerService.runWithTrace
 *   para que todos os logs downstream incluam automaticamente traceId e spanId
 * - Propaga o trace ID nos headers da resposta
 */
@Injectable()
export class TraceIdInterceptor implements NestInterceptor {
  private readonly logger = new Logger(TraceIdInterceptor.name);

  /**
   * Intercepta a requisição, injeta/propaga o trace ID e envolve o handler em um contexto AsyncLocalStorage.
   *
   * @param context - Contexto de execução do NestJS
   * @param next - Próximo handler na cadeia de interceptação
   * @returns Observable que executa o handler dentro do contexto de rastreamento
   */
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
 * Utilitário para obter o trace ID de uma requisição Express.
 *
 * @param request - Objeto de requisição Express
 * @returns Trace ID armazenado na requisição ou undefined se não encontrado
 */
export function getTraceId(request: Request): string | undefined {
  return request['traceId'] as string | undefined;
}
