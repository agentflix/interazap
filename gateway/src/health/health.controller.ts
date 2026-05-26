import { Controller, Get, UsePipes, ValidationPipe } from '@nestjs/common';
import { HealthService } from './health.service';
import { HealthStatus } from './models/health.model';

/**
 * Controller de health check do gateway.
 *
 * Expõe endpoints para verificação de saúde utilizados por Kubernetes
 * e sistemas de monitoramento (liveness probe, readiness probe, deep check).
 */
@Controller({ version: '1', path: 'health' })
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class HealthController {
  constructor(private readonly healthService: HealthService) {}

  /**
   * GET /health
   * Health check básico — retorna status simples para liveness probe.
   *
   * @returns Objeto com status 'ok'
   */
  @Get()
  check(): { status: string } {
    return { status: 'ok' };
  }

  /**
   * GET /health/deep
   * Health check profundo — verifica todas as dependências (Redis, Database).
   *
   * @returns Status detalhado de saúde de todas as dependências
   */
  @Get('deep')
  async deepCheck(): Promise<HealthStatus> {
    return this.healthService.checkAll();
  }

  /**
   * GET /health/ready
   * Readiness probe para Kubernetes — indica se o serviço está pronto para receber tráfego.
   *
   * @returns `{ ready: true }` quando todas as dependências estão saudáveis
   */
  @Get('ready')
  async ready(): Promise<{ ready: boolean }> {
    const health = await this.healthService.checkAll();
    return { ready: health.status === 'healthy' };
  }

  /**
   * GET /health/live
   * Liveness probe para Kubernetes — indica que o processo está em execução.
   *
   * @returns `{ alive: true }` sempre que o processo estiver ativo
   */
  @Get('live')
  live(): { alive: boolean } {
    return { alive: true };
  }
}
