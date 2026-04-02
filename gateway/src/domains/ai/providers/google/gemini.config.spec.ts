import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import { GeminiConfigService } from './gemini.config';

describe('GeminiConfigService', () => {
  const createService = async (config: Record<string, unknown>) => {
    const mockConfigService = {
      get: jest.fn().mockReturnValue(config),
    };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        GeminiConfigService,
        {
          provide: ConfigService,
          useValue: mockConfigService,
        },
      ],
    }).compile();

    return {
      service: module.get<GeminiConfigService>(GeminiConfigService),
      configService: mockConfigService,
    };
  };

  it('should expose Google Gemini config defaults', async () => {
    const { service } = await createService({
      apiKey: 'test-google-key',
      model: 'gemini-2.5-flash',
      timeout: 180000,
      maxRetries: 3,
    });

    expect(service.getApiKey()).toBe('test-google-key');
    expect(service.getDefaultModel()).toBe('gemini-2.5-flash');
    expect(service.getTimeoutMs()).toBe(180000);
    expect(service.getMaxRetries()).toBe(3);
    expect(service.getConfig()).toEqual({
      apiKey: 'test-google-key',
      defaultModel: 'gemini-2.5-flash',
      timeoutMs: 180000,
      maxRetries: 3,
    });
  });

  it('should report google provider as not configured when api key is missing', async () => {
    const { service } = await createService({});

    expect(service.isConfigured()).toBe(false);
  });

  it('should report google provider as configured when api key is present', async () => {
    const { service } = await createService({ apiKey: 'test-key' });

    expect(service.isConfigured()).toBe(true);
  });

  it('should use default values when optional config is missing', async () => {
    const { service } = await createService({ apiKey: 'test-key' });

    expect(service.getDefaultModel()).toBe('gemini-2.5-flash');
    expect(service.getTimeoutMs()).toBe(180000);
    expect(service.getMaxRetries()).toBe(3);
  });

  it('should not throw on onModuleInit when api key is missing', async () => {
    const { service } = await createService({});

    expect(() => service.onModuleInit()).not.toThrow();
  });
});
