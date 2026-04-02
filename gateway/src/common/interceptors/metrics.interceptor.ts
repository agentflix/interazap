import {
  Injectable,
  NestInterceptor,
  ExecutionContext,
  CallHandler,
} from '@nestjs/common';
import { Request, Response } from 'express';
import { Observable, tap } from 'rxjs';
import { MetricsService } from '../../metrics/metrics.service';

/**
 * Records HTTP request metrics (method, URL, status code, duration) via MetricsService.
 */
@Injectable()
export class MetricsInterceptor implements NestInterceptor {
  /**
   * @param metricsService - Service used to record aggregated HTTP metrics.
   */
  constructor(private readonly metricsService: MetricsService) {}

  /**
   * Intercepts HTTP requests, measures execution time, and records metrics.
   *
   * @param context - NestJS execution context.
   * @param next - The next handler in the intercept chain.
   * @returns The observable returned by the next handler.
   */
  intercept(context: ExecutionContext, next: CallHandler): Observable<unknown> {
    if (context.getType() !== 'http') {
      return next.handle();
    }

    const startTime = Date.now();
    const request = context.switchToHttp().getRequest<Request>();

    return next.handle().pipe(
      tap({
        next: () => {
          const response = context.switchToHttp().getResponse<Response>();
          this.recordMetrics(
            request.method,
            request.url,
            response.statusCode,
            startTime,
          );
        },
        error: (error: { status?: number }) => {
          const status = error?.status || 500;
          this.recordMetrics(request.method, request.url, status, startTime);
        },
      }),
    );
  }

  /**
   * Delegates metric recording to MetricsService.
   */
  private recordMetrics(
    method: string,
    url: string,
    status: number,
    startTime: number,
  ): void {
    const duration = Date.now() - startTime;
    this.metricsService.recordHttpRequest(method, url, status, duration);
  }
}
