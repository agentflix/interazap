import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import axios from 'axios';
import { MetaLookupService } from './meta-lookup.service';
import { RedisService } from '../../../infrastructure/redis/redis.service';

jest.mock('axios');
const axiosMock = axios as jest.Mocked<typeof axios>;

describe('MetaLookupService.resolveWabaId', () => {
  let service: MetaLookupService;
  let redisClient: { get: jest.Mock; setex: jest.Mock };
  let redisService: { getClient: jest.Mock };
  let configService: { get: jest.Mock };

  const wabaId = 'WABA_111';
  const cacheKey = `meta:lookup:waba:${wabaId}`;

  beforeEach(async () => {
    redisClient = {
      get: jest.fn().mockResolvedValue(null),
      setex: jest.fn().mockResolvedValue('OK'),
    };
    redisService = { getClient: jest.fn().mockReturnValue(redisClient) };
    configService = {
      get: jest.fn((key: string) => {
        if (key === 'api.url') return 'http://api.local';
        if (key === 'internal.apiKey') return 'secret-key';
        return undefined;
      }),
    };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        MetaLookupService,
        { provide: ConfigService, useValue: configService },
        { provide: RedisService, useValue: redisService },
      ],
    }).compile();

    service = module.get<MetaLookupService>(MetaLookupService);
    axiosMock.get.mockReset();
  });

  it('returns null for empty wabaId without HTTP call', async () => {
    const result = await service.resolveWabaId('');

    expect(result).toBeNull();
    expect(axiosMock.get).not.toHaveBeenCalled();
  });

  it('calls Backend with x-api-key header and caches result for 5min', async () => {
    axiosMock.get.mockResolvedValue({
      data: { tenant_id: 'tenant-A', instance_id: 'inst-A' },
    });

    const result = await service.resolveWabaId(wabaId);

    expect(result).toEqual({ tenantId: 'tenant-A', instanceId: 'inst-A' });
    expect(axiosMock.get).toHaveBeenCalledWith(
      `http://api.local/api/internal/chat/instances/by-waba/${wabaId}`,
      expect.objectContaining({
        headers: expect.objectContaining({
          'x-api-key': 'secret-key',
          Accept: 'application/json',
        }),
        timeout: 5000,
      }),
    );
    expect(redisClient.setex).toHaveBeenCalledWith(
      cacheKey,
      300,
      JSON.stringify({ tenantId: 'tenant-A', instanceId: 'inst-A' }),
    );
  });

  it('returns cached result without HTTP call on cache hit', async () => {
    redisClient.get.mockResolvedValueOnce(
      JSON.stringify({ tenantId: 'tenant-A', instanceId: 'inst-A' }),
    );

    const result = await service.resolveWabaId(wabaId);

    expect(result).toEqual({ tenantId: 'tenant-A', instanceId: 'inst-A' });
    expect(axiosMock.get).not.toHaveBeenCalled();
    expect(redisClient.setex).not.toHaveBeenCalled();
  });

  it('returns null on 404 from Backend', async () => {
    axiosMock.get.mockRejectedValue({
      response: { status: 404 },
      message: 'Not Found',
      isAxiosError: true,
    });

    const result = await service.resolveWabaId(wabaId);

    expect(result).toBeNull();
    expect(redisClient.setex).not.toHaveBeenCalled();
  });

  it('returns null when api.url is not configured', async () => {
    configService.get.mockImplementation((key: string) => {
      if (key === 'internal.apiKey') return 'secret-key';
      return undefined;
    });

    const result = await service.resolveWabaId(wabaId);

    expect(result).toBeNull();
    expect(axiosMock.get).not.toHaveBeenCalled();
  });

  it('returns null when internal.apiKey is not configured', async () => {
    configService.get.mockImplementation((key: string) => {
      if (key === 'api.url') return 'http://api.local';
      return undefined;
    });

    const result = await service.resolveWabaId(wabaId);

    expect(result).toBeNull();
    expect(axiosMock.get).not.toHaveBeenCalled();
  });

  it('returns null when response is malformed (missing tenant_id)', async () => {
    axiosMock.get.mockResolvedValue({
      data: { instance_id: 'inst-A' },
    });

    const result = await service.resolveWabaId(wabaId);

    expect(result).toBeNull();
    expect(redisClient.setex).not.toHaveBeenCalled();
  });
});
