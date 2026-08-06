import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import { MetaConfigService } from './meta.config';

describe('MetaConfigService', () => {
  const createService = async (config: Record<string, unknown>) => {
    const mockConfigService = {
      get: jest.fn().mockReturnValue(config),
    };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        MetaConfigService,
        { provide: ConfigService, useValue: mockConfigService },
      ],
    }).compile();

    return module.get<MetaConfigService>(MetaConfigService);
  };

  describe('onModuleInit', () => {
    it('logs error when META_APP_SECRET is missing', async () => {
      const service = await createService({ verifyToken: 'vt' });

      expect(() => service.onModuleInit()).not.toThrow();
      expect(service.isConfigured()).toBe(false);
    });

    it('logs error when META_VERIFY_TOKEN is missing', async () => {
      const service = await createService({ appSecret: 'as' });

      expect(() => service.onModuleInit()).not.toThrow();
      expect(service.isConfigured()).toBe(false);
    });

    it('accepts valid configuration', async () => {
      const service = await createService({
        appSecret: 'app-secret',
        verifyToken: 'verify-token',
      });

      service.onModuleInit();
      expect(service.isConfigured()).toBe(true);
    });
  });

  describe('isConfigured', () => {
    it('returns false when config is empty (fail-closed)', async () => {
      const service = await createService({});

      expect(service.isConfigured()).toBe(false);
    });

    it('returns false when only appSecret is present', async () => {
      const service = await createService({ appSecret: 'as' });

      expect(service.isConfigured()).toBe(false);
    });

    it('returns true when both appSecret and verifyToken are present', async () => {
      const service = await createService({
        appSecret: 'as',
        verifyToken: 'vt',
      });

      expect(service.isConfigured()).toBe(true);
    });
  });
});
