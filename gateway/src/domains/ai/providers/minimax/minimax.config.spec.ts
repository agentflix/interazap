import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import { MiniMaxConfigService } from './minimax.config';

/**
 * Testes do MiniMaxConfigService
 *
 * Verifica configuração tipada, defaults e validação de estado.
 */
describe('MiniMaxConfigService', () => {
  const createService = async (config: Record<string, unknown>) => {
    const mockConfigService = {
      get: jest.fn().mockReturnValue(config),
    };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        MiniMaxConfigService,
        {
          provide: ConfigService,
          useValue: mockConfigService,
        },
      ],
    }).compile();

    return {
      service: module.get<MiniMaxConfigService>(MiniMaxConfigService),
      configService: mockConfigService,
    };
  };

  it('should expose MiniMax config with provided values', async () => {
    const { service } = await createService({
      apiKey: 'test-minimax-key',
      baseUrl: 'https://custom.minimax.io',
      model: 'MiniMax-M2.5',
      timeout: 120000,
      maxRetries: 5,
    });

    expect(service.getApiKey()).toBe('test-minimax-key');
    expect(service.getBaseUrl()).toBe('https://custom.minimax.io');
    expect(service.getDefaultModel()).toBe('MiniMax-M2.5');
    expect(service.getTimeoutMs()).toBe(120000);
    expect(service.getMaxRetries()).toBe(5);
    expect(service.getConfig()).toEqual({
      apiKey: 'test-minimax-key',
      baseUrl: 'https://custom.minimax.io',
      defaultModel: 'MiniMax-M2.5',
      timeoutMs: 120000,
      maxRetries: 5,
    });
  });

  it('should report provider as not configured when api key is missing', async () => {
    const { service } = await createService({});

    expect(service.isConfigured()).toBe(false);
  });

  it('should report provider as configured when api key is present', async () => {
    const { service } = await createService({ apiKey: 'test-key' });

    expect(service.isConfigured()).toBe(true);
  });

  it('should use default values when optional config is missing', async () => {
    const { service } = await createService({ apiKey: 'test-key' });

    expect(service.getBaseUrl()).toBe('https://api.minimax.io');
    expect(service.getDefaultModel()).toBe('MiniMax-M2.5');
    expect(service.getTimeoutMs()).toBe(180000);
    expect(service.getMaxRetries()).toBe(3);
  });

  it('should not throw on onModuleInit when api key is missing', async () => {
    const { service } = await createService({});

    expect(() => service.onModuleInit()).not.toThrow();
  });
});
