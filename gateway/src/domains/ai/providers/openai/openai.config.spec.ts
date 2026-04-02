import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import { OpenAIConfigService } from './openai.config';

describe('OpenAIConfigService', () => {
  const createService = async (config: Record<string, unknown>) => {
    const mockConfigService = {
      get: jest.fn().mockReturnValue(config),
    };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        OpenAIConfigService,
        {
          provide: ConfigService,
          useValue: mockConfigService,
        },
      ],
    }).compile();

    return {
      service: module.get<OpenAIConfigService>(OpenAIConfigService),
      configService: mockConfigService,
    };
  };

  describe('onModuleInit', () => {
    it('should validate config on module init', async () => {
      const { service, configService } = await createService({
        apiKey: 'sk-test-key',
        apiKeyFallback: 'sk-fallback',
        model: 'gpt-4o',
        embeddingModel: 'text-embedding-3-small',
        timeout: 180000,
        maxRetries: 3,
      });

      service.onModuleInit();

      expect(configService.get).toHaveBeenCalledWith('openai');
    });

    it('should not throw when API key is missing (logs error)', async () => {
      const { service } = await createService({});

      expect(() => service.onModuleInit()).not.toThrow();
    });

    it('should use default values for optional config', async () => {
      const { service } = await createService({
        apiKey: 'sk-test-key',
      });

      service.onModuleInit();

      const config = service.getConfig();
      expect(config.defaultModel).toBe('gpt-4o');
      expect(config.embeddingModel).toBe('text-embedding-3-small');
      expect(config.timeoutMs).toBe(180000);
    });
  });

  describe('getConfig', () => {
    it('should return config object', async () => {
      const { service } = await createService({
        apiKey: 'sk-test-key',
        model: 'gpt-4o',
      });

      const config = service.getConfig();

      expect(config).toHaveProperty('apiKey');
      expect(config).toHaveProperty('defaultModel');
    });
  });

  describe('getApiKey', () => {
    it('should return primary API key', async () => {
      const { service } = await createService({
        apiKey: 'sk-primary',
        apiKeyFallback: 'sk-fallback',
      });

      const apiKey = service.getApiKey();

      expect(apiKey).toBe('sk-primary');
    });
  });

  describe('getFallbackApiKey', () => {
    it('should return fallback API key when configured', async () => {
      const { service } = await createService({
        apiKey: 'sk-primary',
        apiKeyFallback: 'sk-fallback',
      });

      const fallback = service.getFallbackApiKey();

      expect(fallback).toBe('sk-fallback');
    });

    it('should return undefined when no fallback configured', async () => {
      const { service } = await createService({
        apiKey: 'sk-primary',
      });

      const fallback = service.getFallbackApiKey();

      expect(fallback).toBeUndefined();
    });
  });

  describe('hasFallbackKey', () => {
    it('should return true when fallback is configured', async () => {
      const { service } = await createService({
        apiKey: 'sk-primary',
        apiKeyFallback: 'sk-fallback',
      });

      expect(service.hasFallbackKey()).toBe(true);
    });

    it('should return false when no fallback', async () => {
      const { service } = await createService({
        apiKey: 'sk-primary',
      });

      expect(service.hasFallbackKey()).toBe(false);
    });
  });

  describe('isConfigured', () => {
    it('should return true when apiKey is set', async () => {
      const { service } = await createService({
        apiKey: 'sk-test',
      });

      expect(service.isConfigured()).toBe(true);
    });

    it('should return false when apiKey is empty', async () => {
      const { service } = await createService({});

      expect(service.isConfigured()).toBe(false);
    });
  });

  describe('getDefaultModel', () => {
    it('should return configured model', async () => {
      const { service } = await createService({
        apiKey: 'sk-test',
        model: 'gpt-4-turbo',
      });

      expect(service.getDefaultModel()).toBe('gpt-4-turbo');
    });
  });

  describe('getTimeoutMs', () => {
    it('should return configured timeout', async () => {
      const { service } = await createService({
        apiKey: 'sk-test',
        timeout: 60000,
      });

      expect(service.getTimeoutMs()).toBe(60000);
    });
  });

  describe('getMaxRetries', () => {
    it('should return configured max retries', async () => {
      const { service } = await createService({
        apiKey: 'sk-test',
        maxRetries: 5,
      });

      expect(service.getMaxRetries()).toBe(5);
    });
  });
});
