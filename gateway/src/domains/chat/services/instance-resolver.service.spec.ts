import { Test, TestingModule } from '@nestjs/testing';
import {
  ServiceUnavailableException,
  UnauthorizedException,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { InstanceResolverService } from './instance-resolver.service';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { DatabaseService } from '../../../infrastructure/database/database.service';

describe('InstanceResolverService', () => {
  let service: InstanceResolverService;
  let databaseService: jest.Mocked<DatabaseService>;
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

    const mockDatabaseService = {
      query: jest.fn(),
    };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        InstanceResolverService,
        { provide: RedisService, useValue: mockRedisService },
        { provide: DatabaseService, useValue: mockDatabaseService },
        {
          provide: ConfigService,
          useValue: { get: jest.fn().mockReturnValue(undefined) },
        },
      ],
    }).compile();

    service = module.get<InstanceResolverService>(InstanceResolverService);
    databaseService = module.get(DatabaseService);
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
    expect(databaseService.query).not.toHaveBeenCalled();
  });

  it('returns stale cache and triggers background revalidate', async () => {
    const staleInstance = {
      instance_id: 'inst-2',
      tenant_id: 'tenant-2',
      provider: 'uazapi',
    };

    mockRedisClient.get
      .mockResolvedValueOnce(null)
      .mockResolvedValueOnce(JSON.stringify(staleInstance));
    mockRedisClient.set.mockResolvedValue('OK');
    databaseService.query.mockResolvedValue({
      rows: [staleInstance],
      rowCount: 1,
    } as never);

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

  it('queries database on full cache miss and writes active/stale cache', async () => {
    const instance = {
      instance_id: 'inst-3',
      tenant_id: 'tenant-3',
      provider: 'uazapi',
      status: 'connected',
    };

    mockRedisClient.get.mockResolvedValue(null);
    databaseService.query.mockResolvedValue({
      rows: [instance],
      rowCount: 1,
    } as never);

    const result = await service.resolveByWebhookToken('token-db');

    expect(result.instance_id).toBe('inst-3');
    expect(databaseService.query).toHaveBeenCalledTimes(1);
    expect(databaseService.query).toHaveBeenCalledWith(
      expect.stringContaining('webhook_token'),
      ['token-db'],
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
    databaseService.query.mockResolvedValue({
      rows: [],
      rowCount: 0,
    } as never);

    await expect(
      service.resolveByWebhookToken('invalid-token'),
    ).rejects.toThrow(UnauthorizedException);
  });

  it('deduplicates concurrent DB resolution for the same token (single-flight)', async () => {
    const uuidToken = '123e4567-e89b-12d3-a456-426614174000';
    const instance = {
      instance_id: 'inst-4',
      tenant_id: 'tenant-4',
      provider: 'uazapi',
      status: 'connected',
    };

    mockRedisClient.get.mockResolvedValue(null);
    databaseService.query.mockImplementation(
      async (): Promise<{ rows: (typeof instance)[]; rowCount: number }> => {
        await new Promise((resolve) => setTimeout(resolve, 25));
        return { rows: [instance], rowCount: 1 };
      },
    );

    const [resultA, resultB] = await Promise.all([
      service.resolveByWebhookToken(uuidToken),
      service.resolveByWebhookToken(uuidToken),
    ]);

    expect(resultA).toEqual(instance);
    expect(resultB).toEqual(instance);
    expect(databaseService.query).toHaveBeenCalledTimes(1);
    expect(databaseService.query).toHaveBeenCalledWith(
      expect.stringContaining('webhook_token'),
      [uuidToken],
    );
  });

  it('does not execute settings_json token fallback for UUID tokens', async () => {
    const uuidToken = '123e4567-e89b-12d3-a456-426614174000';

    mockRedisClient.get.mockResolvedValue(null);
    databaseService.query.mockResolvedValue({
      rows: [],
      rowCount: 0,
    } as never);

    await expect(service.resolveByWebhookToken(uuidToken)).rejects.toThrow(
      UnauthorizedException,
    );

    expect(databaseService.query).toHaveBeenCalledTimes(1);
    expect(databaseService.query).toHaveBeenCalledWith(
      expect.stringContaining('webhook_token'),
      [uuidToken],
    );
    expect(databaseService.query).not.toHaveBeenCalledWith(
      expect.stringContaining("settings_json->>'token'"),
      [uuidToken],
    );
  });

  it('invalidates active and stale cache keys', async () => {
    mockRedisClient.del.mockResolvedValue(2);

    await service.invalidateByWebhookToken('token-x');

    expect(mockRedisClient.del).toHaveBeenCalledWith(
      'chat.instance_by_webhook_token:token-x:active',
      'chat.instance_by_webhook_token:token-x:stale',
    );
  });

  it('fails fast with ServiceUnavailableException when DB lookup exceeds timeout budget', async () => {
    const previousTimeout = process.env.INSTANCE_RESOLVER_DB_LOOKUP_TIMEOUT_MS;
    process.env.INSTANCE_RESOLVER_DB_LOOKUP_TIMEOUT_MS = '30';

    const timeoutModule: TestingModule = await Test.createTestingModule({
      providers: [
        InstanceResolverService,
        { provide: RedisService, useValue: mockRedisService },
        { provide: DatabaseService, useValue: databaseService },
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
    databaseService.query.mockImplementation(
      async (): Promise<{ rows: never[]; rowCount: number }> => {
        await new Promise((resolve) => setTimeout(resolve, 120));
        return { rows: [], rowCount: 0 };
      },
    );

    await expect(
      timeoutService.resolveByWebhookToken('slow-token'),
    ).rejects.toThrow(ServiceUnavailableException);

    if (previousTimeout === undefined) {
      delete process.env.INSTANCE_RESOLVER_DB_LOOKUP_TIMEOUT_MS;
    } else {
      process.env.INSTANCE_RESOLVER_DB_LOOKUP_TIMEOUT_MS = previousTimeout;
    }
  });
});
