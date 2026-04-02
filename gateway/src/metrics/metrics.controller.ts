import {
  Controller,
  Get,
  Header,
  UsePipes,
  ValidationPipe,
} from '@nestjs/common';
import { MetricsService } from './metrics.service';

/**
 * MetricsController
 *
 * Expõe métricas do sistema em formato Prometheus para scraping.
 * Utilizado para monitoramento e alerting via Alertmanager.
 */
@Controller({ path: 'metrics', version: '1' })
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class MetricsController {
  /**
   * Injects MetricsService for Prometheus metrics retrieval.
   */
  constructor(private readonly metricsService: MetricsService) {}

  /**
   * Returns all collected metrics in Prometheus text format for scraping.
   */
  @Get()
  @Header('Content-Type', 'text/plain; version=0.0.4')
  async metrics(): Promise<string> {
    return this.metricsService.getMetrics();
  }
}
