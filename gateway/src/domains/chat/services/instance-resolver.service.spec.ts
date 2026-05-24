import { Test, TestingModule } from '@nestjs/testing';
import {
  ServiceUnavailableException,
  UnauthorizedException,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { InstanceResolverService } from './instance-resolver.service';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { InternalApiClientService } from '../../../infrastructure/internal-api/internal-api-client.service';

describe('InstanceResolverService', () => {
  let service: InstanceResolverService;
  let internalApiClient: jest.Mocked<{ get: jest.Mock }>;
  type RedisClientMock = {
    get: jest.Mock<Promise<string | null>, [string]>;
    setex: jest.Mock<Promise<void>, [string, number, string]>;
    set: jest.Mock<
      Promise<string | null>,
      [string, string, 'EX', number, 'NX']
    >;
    del: jest.Mock<Promise<number>, [string, string]>;
  };

  let mockRedisClient: RedisClientMock;
  let mockRedisService: { getClient: jest.Mock<RedisClientMock, []> };

  beforeEach(async () => {
    mockRedisClient = {
      get: jest.fn(),
      setex: jest.fn(),
      set: jest.fn(),
      del: jest.fn(),
    };

    mockRedisService = {
      getClient: jest.fn(() => mockRedisClient),
    };

    internalApiClient = {
      get: jest.fn(),
    } as unknown as jest.Mocked<{ get: jest.Mock }>;

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        InstanceResolverService,
        { provide: RedisService, useValue: mockRedisService },
        { provide: InternalApiClientService, useValue: internalApiClient },
        {
          provide: ConfigService,
          useValue: { get: jest.fn().mockReturnValue(undefined) },
        },
      ],
    }).compile();

    service = module.get<InstanceResolverService>(InstanceResolverService);
  });

  it('returns active cache immediately', async () => {
    const cachedInstance = {
      instance_id: 'inst-1',
      tenant_id: 'tenant-1',
      provider: 'zapi',
    };

    mockRedisClient.get
      .mockResolvedValueOnce(JSON.stringify(cachedInstance))
      .mockResolvedValueOnce(null);

    const result = await service.resolveByWebhookToken('token-123');

    expect(result).toEqual(cachedInstance);
    expect(internalApiClient.get).not.toHaveBeenCalled();
  });

  it('returns stale cache and triggers background revalidation', async () => {
    const staleInstance = {
      instance_id: 'inst-2',
      tenant_id: 'tenant-2',
      provider: 'uazapi',
    };

    mockRedisClient.get
      .mockResolvedValueOnce(null)
      .mockResolvedValueOnce(JSON.stringify(staleInstance));
    mockRedisClient.set.mockResolvedValue('OK');
    internalApiClient.get.mockResolvedValue({ data: staleInstance });

    const result = await service.resolveByWebhookToken('token-stale');

    expect(result).toEqual(staleInstance);
    expect(mockRedisClient.set).toHaveBeenCalledWith(
      'chat.instance_by_webhook_token:token-stale:revalidate_lock',
      '1',
      'EX',
      15,
      'NX',
    );
  });

  it('queries api on full cache miss and writes active/stale cache', async () => {
    const instance = {
      instance_id: 'inst-3',
      tenant_id: 'tenant-3',
      provider: 'uazapi',
    };

    mockRedisClient.get.mockResolvedValue(null);
    internalApiClient.get.mockResolvedValue({ data: instance });

    const result = await service.resolveByWebhookToken('token-db');

    expect(result.instance_id).toBe('inst-3');
    expect(internalApiClient.get).toHaveBeenCalledWith(
      expect.stringContaining('by-webhook-token/token-db'),
      'instance_resolve',
    );
    expect(mockRedisClient.setex).toHaveBeenCalledWith(
      'chat.instance_by_webhook_token:token-db:active',
      3600,
      JSON.stringify(instance),
    );
    expect(mockRedisClient.setex).toHaveBeenCalledWith(
      'chat.instance_by_webhook_token:token-db:stale',
      86400,
      JSON.stringify(instance),
    );
  });

  it('throws UnauthorizedException when instance not found', async () => {
    mockRedisClient.get.mockResolvedValue(null);
    internalApiClient.get.mockResolvedValue({ data: null });

    await expect(
      service.resolveByWebhookToken('invalid-token'),
    ).rejects.toThrow(UnauthorizedException);
  });

  it('deduplicates concurrent API resolution for the same token (single-flight)', async () => {
    const instance = {
      instance_id: 'inst-4',
      tenant_id: 'tenant-4',
      provider: 'uazapi',
    };

    mockRedisClient.get.mockResolvedValue(null);
    internalApiClient.get.mockImplementation(
      async (): Promise<{ data: typeof instance }> => {
        await new Promise((resolve) => setTimeout(resolve, 25));
        return { data: instance };
      },
    );

    const [resultA, resultB] = await Promise.all([
      service.resolveByWebhookToken('token-concurrent'),
      service.resolveByWebhookToken('token-concurrent'),
    ]);

    expect(resultA).toEqual(instance);
    expect(resultB).toEqual(instance);
    expect(internalApiClient.get).toHaveBeenCalledTimes(1);
  });

  it('invalidates active and stale cache keys', async () => {
    mockRedisClient.del.mockResolvedValue(2);

    await service.invalidateByWebhookToken('token-x');

    expect(mockRedisClient.del).toHaveBeenCalledWith(
      'chat.instance_by_webhook_token:token-x:active',
      'chat.instance_by_webhook_token:token-x:stale',
    );
  });

  it('fails fast with ServiceUnavailableException when API lookup exceeds timeout budget', async () => {
    const timeoutModule: TestingModule = await Test.createTestingModule({
      providers: [
        InstanceResolverService,
        { provide: RedisService, useValue: mockRedisService },
        { provide: InternalApiClientService, useValue: internalApiClient },
        {
          provide: ConfigService,
          useValue: {
            get: jest.fn((key: string) =>
              key === 'INSTANCE_RESOLVER_DB_LOOKUP_TIMEOUT_MS' ? 30 : undefined,
            ),
          },
        },
      ],
    }).compile();

    const timeoutService = timeoutModule.get<InstanceResolverService>(
      InstanceResolverService,
    );

    mockRedisClient.get.mockResolvedValue(null);
    internalApiClient.get.mockImplementation(
      async (): Promise<{ data: null }> => {
        await new Promise((resolve) => setTimeout(resolve, 120));
        return { data: null };
      },
    );

    await expect(
      timeoutService.resolveByWebhookToken('slow-token'),
    ).rejects.toThrow(ServiceUnavailableException);
  });
});
