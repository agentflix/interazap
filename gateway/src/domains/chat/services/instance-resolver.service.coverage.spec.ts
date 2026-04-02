import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import { InstanceResolverService } from './instance-resolver.service';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { DatabaseService } from '../../../infrastructure/database/database.service';
import { Logger, UnauthorizedException } from '@nestjs/common';

describe('InstanceResolverService Coverage', () => {
  let service: InstanceResolverService;
  let databaseService: any;
  let redisService: any;
  let redisClient: any;

  beforeEach(async () => {
    databaseService = {
      query: jest.fn(),
    };
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
        { provide: DatabaseService, useValue: databaseService },
        { provide: RedisService, useValue: redisService },
        {
          provide: ConfigService,
          useValue: { get: jest.fn().mockReturnValue(undefined) },
        },
        Logger,
      ],
    }).compile();

    service = module.get<InstanceResolverService>(InstanceResolverService);
  });

  it('should throw UnauthorizedException if no instance found by token (empty DB result)', async () => {
    databaseService.query.mockResolvedValue({ rows: [] }); // DB returns empty rows
    await expect(
      service.resolveByWebhookToken('invalid-token'),
    ).rejects.toThrow(UnauthorizedException);
  });

  it('should throw UnauthorizedException if no instance found in DB (malformed result)', async () => {
    databaseService.query.mockResolvedValue(null); // DB returns null
    await expect(
      service.resolveByWebhookToken('invalid-token'),
    ).rejects.toThrow(UnauthorizedException);
  });

  it('should handle cached instance being invalid JSON', async () => {
    redisClient.get.mockResolvedValue('invalid-json');
    databaseService.query.mockResolvedValue({
      rows: [
        {
          instance_id: 'inst-1',
          tenant_id: 'ten-1',
          provider: 'uazapi',
          status: 'active',
        },
      ],
    });

    const result = await service.resolveByWebhookToken('token1');
    expect(result.instance_id).toBe('inst-1');
  });

  it('should handle cached instance missing required fields', async () => {
    redisClient.get.mockResolvedValue(JSON.stringify({ some: 'data' }));
    databaseService.query.mockResolvedValue({
      rows: [
        {
          instance_id: 'inst-1',
          tenant_id: 'ten-1',
          provider: 'uazapi',
          status: 'active',
        },
      ],
    });

    const result = await service.resolveByWebhookToken('token1');
    expect(result.instance_id).toBe('inst-1');
  });
});
