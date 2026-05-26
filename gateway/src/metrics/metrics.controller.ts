import {
  Controller,
  Get,
  Header,
  UseGuards,
  UsePipes,
  ValidationPipe,
} from '@nestjs/common';
import { InternalApiKeyGuard } from '../domains/realtime/guards/internal-api-key.guard';
import { MetricsService } from './metrics.service';

/**
 * Controller que expõe as métricas do gateway em formato Prometheus para scraping.
 *
 * Contexto: módulo metrics. Endpoint utilizado pelo Prometheus para coletar
 * métricas de negócio e infraestrutura do gateway. Protegido pelo guard de
 * API key interna para evitar exposição pública.
 */
@Controller({ path: 'metrics', version: '1' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class MetricsController {
  constructor(private readonly metricsService: MetricsService) {}

  /**
   * GET /metrics
   * Retorna todas as métricas coletadas no formato texto do Prometheus para scraping.
   * @returns String no formato Prometheus text (Content-Type: text/plain; version=0.0.4)
   */
  @Get()
  @Header('Content-Type', 'text/plain; version=0.0.4')
  async metrics(): Promise<string> {
    return this.metricsService.getMetrics();
  }
}
