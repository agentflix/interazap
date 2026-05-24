import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import { InstanceResolverService } from './instance-resolver.service';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { InternalApiClientService } from '../../../infrastructure/internal-api/internal-api-client.service';
import { UnauthorizedException } from '@nestjs/common';

describe('InstanceResolverService Coverage', () => {
  let service: InstanceResolverService;
  let internalApiClient: { get: jest.Mock };
  let redisClient: { get: jest.Mock; setex: jest.Mock };
  let redisService: { getClient: jest.Mock };

  beforeEach(async () => {
    internalApiClient = { get: jest.fn() };
    redisClient = {
      get: jest.fn(),
      setex: jest.fn(),
    };
    redisService = {
      getClient: jest.fn().mockReturnValue(redisClient),
    };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        InstanceResolverService,
        { provide: InternalApiClientService, useValue: internalApiClient },
        { provide: RedisService, useValue: redisService },
        {
          provide: ConfigService,
          useValue: { get: jest.fn().mockReturnValue(undefined) },
        },
      ],
    }).compile();

    service = module.get<InstanceResolverService>(InstanceResolverService);
  });

  it('throws UnauthorizedException when api returns null data', async () => {
    redisClient.get.mockResolvedValue(null);
    internalApiClient.get.mockResolvedValue({ data: null });

    await expect(
      service.resolveByWebhookToken('invalid-token'),
    ).rejects.toThrow(UnauthorizedException);
  });

  it('throws UnauthorizedException when api returns instance without required fields', async () => {
    redisClient.get.mockResolvedValue(null);
    internalApiClient.get.mockResolvedValue({ data: { some: 'data' } });

    await expect(
      service.resolveByWebhookToken('invalid-token'),
    ).rejects.toThrow(UnauthorizedException);
  });

  it('falls through to api when cached instance has invalid JSON', async () => {
    redisClient.get.mockResolvedValue('invalid-json');
    internalApiClient.get.mockResolvedValue({
      data: {
        instance_id: 'inst-1',
        tenant_id: 'ten-1',
        provider: 'uazapi',
      },
    });

    const result = await service.resolveByWebhookToken('token1');
    expect(result.instance_id).toBe('inst-1');
  });

  it('falls through to api when cached instance missing required fields', async () => {
    redisClient.get.mockResolvedValue(JSON.stringify({ some: 'data' }));
    internalApiClient.get.mockResolvedValue({
      data: {
        instance_id: 'inst-1',
        tenant_id: 'ten-1',
        provider: 'uazapi',
      },
    });

    const result = await service.resolveByWebhookToken('token1');
    expect(result.instance_id).toBe('inst-1');
  });
});
