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
 * Registra métricas de requisições HTTP (método, URL, código de status, duração) via MetricsService.
 *
 * Contexto: aplicado globalmente no gateway para monitorar latência e taxa de erros por rota.
 */
@Injectable()
export class MetricsInterceptor implements NestInterceptor {
  /**
   * @param metricsService - Serviço utilizado para registrar métricas HTTP agregadas.
   */
  constructor(private readonly metricsService: MetricsService) {}

  /**
   * Intercepta requisições HTTP, mede o tempo de execução e registra as métricas.
   *
   * @param context - Contexto de execução do NestJS.
   * @param next - Próximo handler na cadeia de interceptação.
   * @returns O observable retornado pelo próximo handler.
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
   * Delega o registro de métricas ao MetricsService.
   *
   * @param method - Método HTTP da requisição
   * @param url - URL da requisição
   * @param status - Código de status HTTP da resposta
   * @param startTime - Timestamp de início da requisição em ms
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
