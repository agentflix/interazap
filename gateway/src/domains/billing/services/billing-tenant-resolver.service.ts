import { Injectable, Logger, UnauthorizedException } from '@nestjs/common';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { DatabaseService } from '../../../infrastructure/database/database.service';
import { isRecord } from '../../../shared/utils/type-guards';
import { ResolvedTenant } from '../models/billing-tenant-resolver.model';

/**
 * BillingTenantResolverService
 *
 * Resolve o tenant a partir do token de webhook de billing, consultando o banco de dados
 * e armazenando o resultado em cache Redis para reduzir latência em requisições subsequentes.
 */
@Injectable()
export class BillingTenantResolverService {
  private readonly logger = new Logger(BillingTenantResolverService.name);
  private readonly cacheTtlSeconds = 120;

  constructor(
    private readonly redisService: RedisService,
    private readonly databaseService: DatabaseService,
  ) {}

  /**
   * Resolve o tenant a partir do token de webhook de billing.
   * Consulta o cache Redis antes de acessar o banco de dados.
   *
   * @param token - Token de webhook do tenant
   * @returns Tenant resolvido com tenant_id e nome
   * @throws UnauthorizedException se nenhum tenant for encontrado para o token
   */
  async resolveByWebhookToken(token: string): Promise<ResolvedTenant> {
    const cacheKey = `billing.tenant_by_webhook_token:${token}`;
    const cached = await this.redisService.getClient().get(cacheKey);
    if (cached) {
      const cachedTenant = this.parseCachedTenant(cached);
      if (cachedTenant) {
        return cachedTenant;
      }
    }

    const rawResult: unknown = await this.databaseService.query<ResolvedTenant>(
      'SELECT id as tenant_id, name FROM platform_tenants WHERE billing_webhook_token = $1 LIMIT 1',
      [token],
    );

    const rows = this.extractRows(rawResult);
    const tenant = rows[0];
    if (!tenant) {
      this.logger.warn('Billing tenant not found for token');
      throw new UnauthorizedException('Invalid billing webhook token');
    }

    await this.redisService
      .getClient()
      .setex(cacheKey, this.cacheTtlSeconds, JSON.stringify(tenant));

    return tenant;
  }

  /**
   * Extrai as linhas de resultado de uma query genérica retornada pelo DatabaseService.
   *
   * @param value - Resultado bruto da query
   * @returns Array somente leitura de tenants resolvidos
   */
  private extractRows(value: unknown): ReadonlyArray<ResolvedTenant> {
    if (!isRecord(value)) {
      return [];
    }
    const rows = value.rows;
    if (!Array.isArray(rows)) {
      return [];
    }

    const isTenant = (row: unknown): row is ResolvedTenant =>
      this.isResolvedTenant(row);

    return rows.filter(isTenant);
  }

  /**
   * Faz o parse do tenant armazenado em cache Redis.
   *
   * @param value - String JSON armazenada no cache
   * @returns Tenant parseado ou null caso o valor seja inválido
   */
  private parseCachedTenant(value: string): ResolvedTenant | null {
    try {
      const parsed = JSON.parse(value) as unknown;
      if (this.isResolvedTenant(parsed)) {
        return parsed;
      }
    } catch {
      this.logger.warn('Invalid cached tenant payload');
    }
    return null;
  }

  /**
   * Type guard para verificar se um valor desconhecido é um ResolvedTenant válido.
   *
   * @param value - Valor a ser verificado
   * @returns true se o valor possui a estrutura de ResolvedTenant
   */
  private isResolvedTenant(value: unknown): value is ResolvedTenant {
    if (!isRecord(value)) {
      return false;
    }

    return typeof value.tenant_id === 'string';
  }
}
