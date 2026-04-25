import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import { ServiceUnavailableException } from '@nestjs/common';
import { SecretsService } from '../../src/common/secrets/secrets.service';

// Mock @aws-sdk/client-secrets-manager before imports (virtual: module not installed)
const awsSendMockFn = jest.fn();
jest.mock(
  '@aws-sdk/client-secrets-manager',
  () => ({
    SecretsManagerClient: jest.fn().mockImplementation(() => ({
      send: awsSendMockFn,
    })),
    GetSecretValueCommand: jest.fn().mockImplementation((input: any) => input),
  }),
  { virtual: true },
);

// Mock global fetch for Vault calls
const fetchMock = jest.fn();
global.fetch = fetchMock as unknown as typeof fetch;

describe('SecretsService', () => {
  let service: SecretsService;
  let configService: { get: jest.Mock };
  let logSpy: jest.SpyInstance;
  let warnSpy: jest.SpyInstance;

  beforeEach(async () => {
    configService = {
      get: jest.fn().mockReturnValue(undefined),
    };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        SecretsService,
        { provide: ConfigService, useValue: configService },
      ],
    }).compile();

    service = module.get<SecretsService>(SecretsService);

    // Access internal logger via prototype spy
    logSpy = jest.spyOn((service as any).logger, 'log').mockImplementation();
    warnSpy = jest.spyOn((service as any).logger, 'warn').mockImplementation();

    // Reset AWS mock
    awsSendMockFn.mockReset();

    fetchMock.mockReset();
  });

  afterEach(() => {
    // Clear cache between tests
    service.invalidateAll();
    jest.restoreAllMocks();
  });

  // ─── Cache Behavior ──────────────────────────────────────

  describe('cache', () => {
    it('returns cached value within TTL', async () => {
      configService.get.mockReturnValue(undefined);
      // Set ENV as fallback so first call resolves
      configService.get.mockImplementation((key: string) => {
        if (key === 'MY_KEY') return 'env-value';
        return undefined;
      });

      const first = await service.getSecret('MY_KEY');
      // Change env to prove second call uses cache, not re-resolve
      configService.get.mockReturnValue(undefined);
      const second = await service.getSecret('MY_KEY');

      expect(first).toBe('env-value');
      expect(second).toBe('env-value');
    });

    it('expires cache after TTL (5 min)', async () => {
      const realNow = Date.now;
      let currentTime = 1_000_000;
      Date.now = jest.fn(() => currentTime);

      configService.get.mockImplementation((key: string) => {
        if (key === 'TEST_KEY') return 'env-value';
        return undefined;
      });

      await service.getSecret('TEST_KEY');
      expect(logSpy).toHaveBeenCalledTimes(1);

      // Advance past TTL (5 min = 300_000ms)
      currentTime += 300_001;

      // After TTL, cache expired — resolves again from ENV
      const result = await service.getSecret('TEST_KEY');
      expect(result).toBe('env-value');
      // Log called twice (once per resolution, not from cache)
      expect(logSpy).toHaveBeenCalledTimes(2);

      Date.now = realNow;
    });

    it('invalidateCache removes specific key', async () => {
      configService.get.mockImplementation((key: string) => {
        if (key === 'KEY_A') return 'value-a';
        if (key === 'KEY_B') return 'value-b';
        return undefined;
      });

      await service.getSecret('KEY_A');
      await service.getSecret('KEY_B');

      service.invalidateCache('KEY_A');

      // KEY_A re-resolves (log called again), KEY_B still cached
      logSpy.mockClear();
      await service.getSecret('KEY_A');
      await service.getSecret('KEY_B');

      // Only KEY_A should have triggered a new log
      expect(logSpy).toHaveBeenCalledTimes(1);
      expect(logSpy).toHaveBeenCalledWith(expect.stringContaining('KEY_A'));
    });

    it('invalidateAll clears all cache', async () => {
      configService.get.mockImplementation((key: string) => {
        if (key === 'K1') return 'v1';
        if (key === 'K2') return 'v2';
        return undefined;
      });

      await service.getSecret('K1');
      await service.getSecret('K2');

      service.invalidateAll();
      logSpy.mockClear();

      await service.getSecret('K1');
      await service.getSecret('K2');

      // Both re-resolved
      expect(logSpy).toHaveBeenCalledTimes(2);
    });
  });

  // ─── Fallthrough Chain ───────────────────────────────────

  describe('fallthrough chain (AWS → Vault → ENV)', () => {
    it('resolves from AWS SM when configured', async () => {
      // Spy on private method to avoid dynamic import issues
      jest
        .spyOn(service as any, 'fetchFromAwsSecretsManager')
        .mockResolvedValueOnce('aws-secret-value');

      const result = await service.getSecret('my/secret');
      expect(result).toBe('aws-secret-value');
      expect(logSpy).toHaveBeenCalledWith(expect.stringContaining('AWS_SM'));
    });

    it('falls through to Vault when AWS fails', async () => {
      // AWS returns null (simulating failure/not configured)
      jest
        .spyOn(service as any, 'fetchFromAwsSecretsManager')
        .mockResolvedValueOnce(null);

      configService.get.mockImplementation((key: string) => {
        if (key === 'VAULT_ADDR') return 'http://vault:8200';
        if (key === 'VAULT_TOKEN') return 'hvs.test';
        return undefined;
      });

      fetchMock.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          data: { data: { value: 'vault-secret' } },
        }),
      });

      const result = await service.getSecret('my/secret');
      expect(result).toBe('vault-secret');
      expect(logSpy).toHaveBeenCalledWith(expect.stringContaining('VAULT'));
    });

    it('falls through to ENV when both AWS and Vault fail', async () => {
      jest
        .spyOn(service as any, 'fetchFromAwsSecretsManager')
        .mockResolvedValueOnce(null);
      jest.spyOn(service as any, 'fetchFromVault').mockResolvedValueOnce(null);

      configService.get.mockImplementation((key: string) => {
        if (key === 'MY_SECRET_KEY') return 'env-fallback';
        return undefined;
      });

      const result = await service.getSecret('MY_SECRET_KEY');
      expect(result).toBe('env-fallback');
      expect(logSpy).toHaveBeenCalledWith(expect.stringContaining('ENV'));
    });

    it('throws ServiceUnavailableException when all sources fail', async () => {
      // No AWS config, no Vault config, no ENV
      configService.get.mockReturnValue(undefined);

      await expect(service.getSecret('missing/key')).rejects.toThrow(
        ServiceUnavailableException,
      );
    });
  });

  // ─── getBotToken ─────────────────────────────────────────

  describe('getBotToken', () => {
    it('resolves correct key pattern telegram/bot-token/{instanceId}', async () => {
      const getSecretSpy = jest
        .spyOn(service, 'getSecret')
        .mockResolvedValueOnce('bot-token-123');

      const result = await service.getBotToken('instance-42');

      expect(result).toBe('bot-token-123');
      expect(getSecretSpy).toHaveBeenCalledWith(
        'telegram/bot-token/instance-42',
      );
    });
  });

  // ─── Security: No secret values in logs ──────────────────

  describe('security', () => {
    it('does NOT log secret values', async () => {
      configService.get.mockImplementation((key: string) => {
        if (key === 'super-secret') return 'VERY_SENSITIVE_VALUE';
        return undefined;
      });

      await service.getSecret('super-secret');

      // Check that logger.log calls never include the actual secret value
      for (const call of logSpy.mock.calls) {
        expect(call[0]).not.toContain('VERY_SENSITIVE_VALUE');
      }
      for (const call of warnSpy.mock.calls) {
        expect(call[0]).not.toContain('VERY_SENSITIVE_VALUE');
      }
    });

    it('logs source name (AWS/Vault/ENV) on resolution', async () => {
      configService.get.mockImplementation((key: string) => {
        if (key === 'test-key') return 'some-value';
        return undefined;
      });

      await service.getSecret('test-key');

      expect(logSpy).toHaveBeenCalledWith(
        expect.stringMatching(/resolved from: (AWS_SM|VAULT|ENV)/),
      );
    });
  });
});
