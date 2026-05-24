import { Test, TestingModule } from '@nestjs/testing';
import { UnauthorizedException } from '@nestjs/common';
import { BillingTenantResolverService } from './billing-tenant-resolver.service';
import { InternalApiClientService } from '../../../infrastructure/internal-api/internal-api-client.service';
import { GatewayCacheService } from '../../../infrastructure/cache/gateway-cache.service';

describe('BillingTenantResolverService', () => {
  let service: BillingTenantResolverService;
  let cacheService: { getOrFetch: jest.Mock };
  let internalApiClient: { get: jest.Mock };

  beforeEach(async () => {
    internalApiClient = { get: jest.fn() };
    cacheService = { getOrFetch: jest.fn() };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        BillingTenantResolverService,
        { provide: InternalApiClientService, useValue: internalApiClient },
        { provide: GatewayCacheService, useValue: cacheService },
      ],
    }).compile();

    service = module.get<BillingTenantResolverService>(
      BillingTenantResolverService,
    );
  });

  describe('resolveByWebhookToken', () => {
    it('returns resolved tenant from cache hit', async () => {
      const tenantData = {
        tenant_id: 'tenant-1',
        plan: 'pro',
        quota_monthly: 1000,
        overflow_mode: 'stop',
      };
      cacheService.getOrFetch.mockResolvedValue(tenantData);

      const result = await service.resolveByWebhookToken('billing-token-123');

      expect(result).toEqual({ tenant_id: 'tenant-1' });
      expect(cacheService.getOrFetch).toHaveBeenCalledWith(
        'billing:tenant:billing-token-123',
        expect.any(Function),
        expect.objectContaining({ l1TtlMs: expect.any(Number) }),
        'billing_tenant_resolve',
      );
    });

    it('calls api on cache miss (via fetcher function)', async () => {
      const tenantData = {
        tenant_id: 'tenant-1',
        plan: 'basic',
        quota_monthly: 100,
        overflow_mode: 'stop',
      };

      cacheService.getOrFetch.mockImplementation(
        async (_key: string, fetcher: () => Promise<typeof tenantData>) => {
          return fetcher();
        },
      );
      internalApiClient.get.mockResolvedValue({
        success: true,
        data: tenantData,
      });

      const result = await service.resolveByWebhookToken('billing-token-123');

      expect(result.tenant_id).toBe('tenant-1');
      expect(internalApiClient.get).toHaveBeenCalledWith(
        expect.stringContaining('by-webhook-token/billing-token-123'),
        'billing_tenant_resolve',
      );
    });

    it('throws UnauthorizedException when cache/api throws', async () => {
      cacheService.getOrFetch.mockRejectedValue(new Error('not found'));

      await expect(
        service.resolveByWebhookToken('invalid-token'),
      ).rejects.toThrow(UnauthorizedException);

      await expect(
        service.resolveByWebhookToken('invalid-token'),
      ).rejects.toThrow('Invalid billing webhook token');
    });
  });
});
