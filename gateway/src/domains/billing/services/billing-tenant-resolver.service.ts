import { Injectable, Logger, UnauthorizedException } from '@nestjs/common';
import { InternalApiClientService } from '../../../infrastructure/internal-api/internal-api-client.service';
import { GatewayCacheService } from '../../../infrastructure/cache/gateway-cache.service';
import { CacheStrategies } from '../../../infrastructure/cache/gateway-cache.types';
import { ResolvedTenant } from '../models/billing-tenant-resolver.model';

interface TenantResponse {
  tenant_id: string;
  plan: string;
  quota_monthly: number;
  overflow_mode: string;
}

/**
 * BillingTenantResolverService
 *
 * Resolve o tenant a partir do token de webhook de billing via api/ HTTP.
 * Cache L1+L2 com estratégia HOT (L1 30s, L2 120s).
 */
@Injectable()
export class BillingTenantResolverService {
  private readonly logger = new Logger(BillingTenantResolverService.name);

  constructor(
    private readonly internalApiClient: InternalApiClientService,
    private readonly cacheService: GatewayCacheService,
  ) {}

  async resolveByWebhookToken(token: string): Promise<ResolvedTenant> {
    const cacheKey = `billing:tenant:${token}`;

    try {
      const response = await this.cacheService.getOrFetch<TenantResponse>(
        cacheKey,
        async () => {
          const raw = await this.internalApiClient.get<{
            success: boolean;
            data: TenantResponse;
          }>(
            `/api/internal/billing/tenants/by-webhook-token/${encodeURIComponent(token)}`,
            'billing_tenant_resolve',
          );
          return raw.data;
        },
        CacheStrategies.HOT,
        'billing_tenant_resolve',
      );

      return { tenant_id: response.tenant_id };
    } catch (error) {
      this.logger.warn(
        `Billing tenant not found for token: ${error instanceof Error ? error.message : String(error)}`,
      );
      throw new UnauthorizedException('Invalid billing webhook token');
    }
  }
}
