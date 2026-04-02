import { ConfigService } from '@nestjs/config';
import { Logger } from '@nestjs/common';
import axios from 'axios';
import { WsAuthenticationService } from './ws-authentication.service';

jest.mock('axios');

describe('WsAuthenticationService', () => {
  const mockedAxios = axios as jest.Mocked<typeof axios>;
  let configService: ConfigService;
  let logger: Logger;

  const createConfigService = (
    overrides: Record<string, string | number | undefined> = {},
  ): ConfigService =>
    ({
      get: jest.fn((key: string) => {
        if (key in overrides) {
          return overrides[key];
        }

        if (key === 'api.url') return 'http://api.local';
        if (key === 'api.authTimeoutMs') return 1500;
        if (key === 'jwt.secret') return undefined;
        return undefined;
      }),
    }) as unknown as ConfigService;

  beforeEach(() => {
    jest.useFakeTimers();
    jest.setSystemTime(new Date('2026-03-20T12:00:00.000Z'));

    configService = createConfigService();

    logger = {
      debug: jest.fn(),
    } as unknown as Logger;

    mockedAxios.get.mockResolvedValue({
      data: {
        success: true,
        data: {
          user: {
            id: 'user-1',
            tenant_id: 'tenant-1',
            email: 'dev@interazap.test',
          },
        },
      },
    });
  });

  afterEach(() => {
    jest.useRealTimers();
    jest.clearAllMocks();
  });

  it('uses fallback ttl of 5 minutes when env is missing/invalid', () => {
    const service = new WsAuthenticationService(configService, logger);
    const ttl = (service as unknown as { sanctumTokenCacheTtlMs: number })
      .sanctumTokenCacheTtlMs;

    expect(ttl).toBe(300_000);
  });

  it('clamps ttl to 5-10 minutes based on env values', () => {
    const belowMinService = new WsAuthenticationService(
      createConfigService({ WS_SANCTUM_CACHE_TTL_MS: '120000' }),
      logger,
    );
    const belowMinTtl = (
      belowMinService as unknown as { sanctumTokenCacheTtlMs: number }
    ).sanctumTokenCacheTtlMs;

    const aboveMaxService = new WsAuthenticationService(
      createConfigService({ WS_SANCTUM_CACHE_TTL_MS: '900000' }),
      logger,
    );
    const aboveMaxTtl = (
      aboveMaxService as unknown as { sanctumTokenCacheTtlMs: number }
    ).sanctumTokenCacheTtlMs;

    const inRangeService = new WsAuthenticationService(
      createConfigService({ WS_SANCTUM_CACHE_TTL_MS: '420000' }),
      logger,
    );
    const inRangeTtl = (
      inRangeService as unknown as { sanctumTokenCacheTtlMs: number }
    ).sanctumTokenCacheTtlMs;

    expect(belowMinTtl).toBe(300_000);
    expect(aboveMaxTtl).toBe(600_000);
    expect(inRangeTtl).toBe(420_000);
  });

  it('revalidates sanctum token after cache ttl expiration', async () => {
    const service = new WsAuthenticationService(configService, logger);

    await service.verifyToken('sanctum-token-1');
    await service.verifyToken('sanctum-token-1');

    expect(mockedAxios.get).toHaveBeenCalledTimes(1);

    jest.advanceTimersByTime(300_001);

    await service.verifyToken('sanctum-token-1');

    expect(mockedAxios.get).toHaveBeenCalledTimes(2);
  });

  it('evicts least recently used token when cache exceeds max entries', () => {
    const service = new WsAuthenticationService(configService, logger);
    const serviceRef = service as unknown as {
      sanctumTokenCacheMaxEntries: number;
      sanctumTokenCache: Map<string, { expiresAt: number }>;
      getCachedToken: (token: string, now: number) => unknown;
      setCachedToken: (
        token: string,
        entry: {
          payload: { sub: string; tenant_id: string };
          expiresAt: number;
        },
      ) => void;
    };

    Object.defineProperty(serviceRef, 'sanctumTokenCacheMaxEntries', {
      value: 2,
      writable: true,
      configurable: true,
    });

    const future = Date.now() + 600_000;
    serviceRef.setCachedToken('token-a', {
      payload: { sub: '1', tenant_id: 't1' },
      expiresAt: future,
    });
    serviceRef.setCachedToken('token-b', {
      payload: { sub: '2', tenant_id: 't1' },
      expiresAt: future,
    });

    // Access token-a to make token-b the least recently used entry.
    serviceRef.getCachedToken('token-a', Date.now());

    serviceRef.setCachedToken('token-c', {
      payload: { sub: '3', tenant_id: 't1' },
      expiresAt: future,
    });

    expect(serviceRef.sanctumTokenCache.has('token-a')).toBe(true);
    expect(serviceRef.sanctumTokenCache.has('token-c')).toBe(true);
    expect(serviceRef.sanctumTokenCache.has('token-b')).toBe(false);
    expect(serviceRef.sanctumTokenCache.size).toBe(2);
  });
});
