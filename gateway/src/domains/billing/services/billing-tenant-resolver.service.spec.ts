import { Test, TestingModule } from '@nestjs/testing';
import { UnauthorizedException } from '@nestjs/common';
import { BillingTenantResolverService } from './billing-tenant-resolver.service';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { DatabaseService } from '../../../infrastructure/database/database.service';
import { DatabaseQueryResult } from '../../../infrastructure/models/database.model';

describe('BillingTenantResolverService', () => {
  let service: BillingTenantResolverService;
  let databaseService: jest.Mocked<DatabaseService>;
  type RedisClientMock = {
    get: jest.Mock<Promise<string | null>, [string]>;
    setex: jest.Mock<Promise<void>, [string, number, string]>;
  };

  let mockRedisClient: RedisClientMock;
  let mockRedisService: { getClient: jest.Mock<RedisClientMock, []> };

  beforeEach(async () => {
    mockRedisClient = {
      get: jest.fn(),
      setex: jest.fn(),
    };

    mockRedisService = {
      getClient: jest.fn(() => mockRedisClient),
    };

    const mockDatabaseService = {
      query: jest.fn(),
    };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        BillingTenantResolverService,
        { provide: RedisService, useValue: mockRedisService },
        { provide: DatabaseService, useValue: mockDatabaseService },
      ],
    }).compile();

    service = module.get<BillingTenantResolverService>(
      BillingTenantResolverService,
    );
    databaseService = module.get(DatabaseService);
  });

  describe('resolveByWebhookToken', () => {
    it('should return cached tenant if available', async () => {
      const cachedTenant = {
        tenant_id: 'tenant-1',
        name: 'Test Tenant',
      };

      mockRedisClient.get.mockResolvedValue(JSON.stringify(cachedTenant));

      const result = await service.resolveByWebhookToken('billing-token-123');

      expect(result).toEqual(cachedTenant);
      expect(databaseService.query).not.toHaveBeenCalled();
    });

    it('should query database when cache miss', async () => {
      mockRedisClient.get.mockResolvedValue(null);
      const queryResult: DatabaseQueryResult<{
        tenant_id: string;
        name: string;
      }> = {
        rows: [
          {
            tenant_id: 'tenant-1',
            name: 'Test Tenant',
          },
        ],
        rowCount: 1,
      };
      databaseService.query.mockResolvedValue(queryResult);

      const result = await service.resolveByWebhookToken('billing-token-123');

      expect(result.tenant_id).toBe('tenant-1');
      expect(result.name).toBe('Test Tenant');
      expect(databaseService.query).toHaveBeenCalledWith(
        expect.stringContaining('platform_tenants'),
        ['billing-token-123'],
      );
    });

    it('should cache result after database query', async () => {
      mockRedisClient.get.mockResolvedValue(null);
      const tenant = {
        tenant_id: 'tenant-1',
        name: 'Test Tenant',
      };

      const queryResult: DatabaseQueryResult<{
        tenant_id: string;
        name: string;
      }> = {
        rows: [tenant],
        rowCount: 1,
      };
      databaseService.query.mockResolvedValue(queryResult);

      await service.resolveByWebhookToken('billing-token-123');

      expect(mockRedisClient.setex).toHaveBeenCalledWith(
        'billing.tenant_by_webhook_token:billing-token-123',
        120,
        JSON.stringify(tenant),
      );
    });

    it('should throw UnauthorizedException when tenant not found', async () => {
      mockRedisClient.get.mockResolvedValue(null);
      const queryResult: DatabaseQueryResult<{
        tenant_id: string;
        name: string;
      }> = {
        rows: [],
        rowCount: 0,
      };
      databaseService.query.mockResolvedValue(queryResult);

      await expect(
        service.resolveByWebhookToken('invalid-token'),
      ).rejects.toThrow(UnauthorizedException);

      await expect(
        service.resolveByWebhookToken('invalid-token'),
      ).rejects.toThrow('Invalid billing webhook token');
    });

    it('should handle tenant without name', async () => {
      mockRedisClient.get.mockResolvedValue(null);
      const queryResult: DatabaseQueryResult<{
        tenant_id: string;
        name: string | null;
      }> = {
        rows: [
          {
            tenant_id: 'tenant-1',
            name: null,
          },
        ],
        rowCount: 1,
      };
      databaseService.query.mockResolvedValue(queryResult);

      const result = await service.resolveByWebhookToken('token');

      expect(result.tenant_id).toBe('tenant-1');
      expect(result.name).toBeNull();
    });

    it('should handle invalid cached data gracefully', async () => {
      mockRedisClient.get.mockResolvedValue('invalid-json');
      const queryResult: DatabaseQueryResult<{
        tenant_id: string;
        name: string;
      }> = {
        rows: [{ tenant_id: 'tenant-1', name: 'Test' }],
        rowCount: 1,
      };
      databaseService.query.mockResolvedValue(queryResult);

      const result = await service.resolveByWebhookToken('token-123');

      expect(result.tenant_id).toBe('tenant-1');
      expect(databaseService.query).toHaveBeenCalled();
    });
  });
});
