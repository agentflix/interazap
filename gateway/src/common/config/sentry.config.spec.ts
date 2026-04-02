import { ConfigService } from '@nestjs/config';
import { getSentryConfig } from './sentry.config';

describe('SentryConfig', () => {
  const createMockConfigService = (
    config: Record<string, string | undefined>,
  ): ConfigService => {
    return {
      get: jest.fn((key: string, defaultValue?: string) => {
        return config[key] !== undefined ? config[key] : defaultValue;
      }),
    } as unknown as ConfigService;
  };

  describe('getSentryConfig', () => {
    it('should return config with all values from environment', () => {
      const configService = createMockConfigService({
        SENTRY_DSN: 'https://test@sentry.io/123',
        NODE_ENV: 'production',
        SENTRY_RELEASE: '2.0.0',
        SENTRY_TRACES_SAMPLE_RATE: '0.5',
        SENTRY_PROFILES_SAMPLE_RATE: '0.3',
      });

      const result = getSentryConfig(configService);

      expect(result.dsn).toBe('https://test@sentry.io/123');
      expect(result.environment).toBe('production');
      expect(result.release).toBe('2.0.0');
      expect(result.tracesSampleRate).toBe(0.5);
      expect(result.profilesSampleRate).toBe(0.3);
      expect(result.debug).toBe(false);
    });

    it('should use default values when not configured', () => {
      const configService = createMockConfigService({});

      const result = getSentryConfig(configService);

      expect(result.dsn).toBeUndefined();
      expect(result.environment).toBe('development');
      expect(result.release).toBe('1.0.0');
      expect(result.tracesSampleRate).toBe(0.0);
      expect(result.profilesSampleRate).toBe(0.0);
      expect(result.debug).toBe(false);
    });

    it('should set debug to true in development', () => {
      const configService = createMockConfigService({
        NODE_ENV: 'development',
      });

      const result = getSentryConfig(configService);

      expect(result.debug).toBe(true);
    });

    it('should set debug to false in production', () => {
      const configService = createMockConfigService({
        NODE_ENV: 'production',
      });

      const result = getSentryConfig(configService);

      expect(result.debug).toBe(false);
    });

    it('should parse trace sample rate correctly', () => {
      const configService = createMockConfigService({
        SENTRY_TRACES_SAMPLE_RATE: '1.0',
        SENTRY_PROFILES_SAMPLE_RATE: '0.75',
      });

      const result = getSentryConfig(configService);

      expect(result.tracesSampleRate).toBe(1.0);
      expect(result.profilesSampleRate).toBe(0.75);
    });

    it('should handle staging environment', () => {
      const configService = createMockConfigService({
        NODE_ENV: 'staging',
        SENTRY_DSN: 'https://staging@sentry.io/456',
      });

      const result = getSentryConfig(configService);

      expect(result.environment).toBe('staging');
      expect(result.dsn).toBe('https://staging@sentry.io/456');
      expect(result.debug).toBe(false);
    });
  });
});
