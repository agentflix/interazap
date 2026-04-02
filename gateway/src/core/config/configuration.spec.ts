import {
  appConfig,
  redisConfig,
  databaseConfig,
  uazapiConfig,
  zapiConfig,
  openaiConfig,
  asaasConfig,
  aiConfig,
} from './configuration';

describe('Configuration', () => {
  const originalEnv = process.env;

  beforeEach(() => {
    jest.resetModules();
    process.env = { ...originalEnv };
  });

  afterAll(() => {
    process.env = originalEnv;
  });

  describe('appConfig', () => {
    it('should return default values when env vars are not set', () => {
      delete process.env.PORT;
      delete process.env.NODE_ENV;

      const config = appConfig();
      expect(config.port).toBe(3000);
      expect(config.nodeEnv).toBe('development');
    });

    it('should parse PORT as integer', () => {
      process.env.PORT = '8080';
      process.env.NODE_ENV = 'production';

      const config = appConfig();
      expect(config.port).toBe(8080);
      expect(config.nodeEnv).toBe('production');
    });
  });

  describe('redisConfig', () => {
    it('should return default URL when not set', () => {
      delete process.env.REDIS_URL;

      const config = redisConfig();
      expect(config.url).toBe('redis://localhost:6379');
    });

    it('should use REDIS_URL from env', () => {
      process.env.REDIS_URL = 'redis://custom:6380';

      const config = redisConfig();
      expect(config.url).toBe('redis://custom:6380');
    });
  });

  describe('databaseConfig', () => {
    it('should return default URL when not set', () => {
      delete process.env.DATABASE_URL;

      const config = databaseConfig();
      expect(config.url).toBe(
        'postgres://interazap:secret@localhost:5432/interazap',
      );
    });

    it('should use DATABASE_URL from env', () => {
      process.env.DATABASE_URL = 'postgres://user:pass@host:5432/db';

      const config = databaseConfig();
      expect(config.url).toBe('postgres://user:pass@host:5432/db');
    });
  });

  describe('uazapiConfig', () => {
    it('should return defaults when env vars are not set', () => {
      delete process.env.UAZAPI_BASE_URL;
      delete process.env.UAZAPI_ADMIN_TOKEN;
      delete process.env.UAZAPI_WEBHOOK_URL;
      delete process.env.UAZAPI_WEBHOOK_EVENTS;
      delete process.env.UAZAPI_WEBHOOK_EXCLUDE_MESSAGES;
      delete process.env.UAZAPI_WEBHOOK_RETRIES;

      const config = uazapiConfig();
      expect(config.baseUrl).toBe('https://free.uazapi.com');
      expect(config.adminToken).toBe('');
      expect(config.webhookUrl).toBe('');
      expect(config.webhookEvents).toEqual([
        'connection',
        'messages',
        'messages_update',
      ]);
      expect(config.webhookExcludeMessages).toEqual([
        'wasSentByApi',
        'isGroupYes',
      ]);
      expect(config.webhookRetries).toBe(3);
    });

    it('should parse custom values', () => {
      process.env.UAZAPI_BASE_URL = 'https://custom.uazapi.com';
      process.env.UAZAPI_ADMIN_TOKEN = 'my-token';
      process.env.UAZAPI_WEBHOOK_URL = 'https://webhook.example.com';
      process.env.UAZAPI_WEBHOOK_EVENTS = 'messages';
      process.env.UAZAPI_WEBHOOK_EXCLUDE_MESSAGES = 'isGroup';
      process.env.UAZAPI_WEBHOOK_RETRIES = '5';

      const config = uazapiConfig();
      expect(config.baseUrl).toBe('https://custom.uazapi.com');
      expect(config.adminToken).toBe('my-token');
      expect(config.webhookUrl).toBe('https://webhook.example.com');
      expect(config.webhookEvents).toEqual(['messages']);
      expect(config.webhookExcludeMessages).toEqual(['isGroup']);
      expect(config.webhookRetries).toBe(5);
    });
  });

  describe('zapiConfig', () => {
    it('should return defaults when env vars are not set', () => {
      delete process.env.ZAPI_BASE_URL;
      delete process.env.ZAPI_CLIENT_TOKEN;
      delete process.env.ZAPI_HTTP_TIMEOUT;

      const config = zapiConfig();
      expect(config.baseUrl).toBe('https://api.z-api.io');
      expect(config.clientToken).toBe('');
      expect(config.timeout).toBe(30000);
    });

    it('should use custom values', () => {
      process.env.ZAPI_BASE_URL = 'https://custom.z-api.io';
      process.env.ZAPI_CLIENT_TOKEN = 'zapi-token';
      process.env.ZAPI_HTTP_TIMEOUT = '45000';

      const config = zapiConfig();
      expect(config.baseUrl).toBe('https://custom.z-api.io');
      expect(config.clientToken).toBe('zapi-token');
      expect(config.timeout).toBe(45000);
    });
  });

  describe('openaiConfig', () => {
    it('should return defaults when env vars are not set', () => {
      delete process.env.OPENAI_API_KEY;
      delete process.env.OPENAI_MODEL;
      delete process.env.OPENAI_DEFAULT_MODEL;
      delete process.env.OPENAI_EMBEDDING_MODEL;
      delete process.env.OPENAI_TIMEOUT;
      delete process.env.OPENAI_MAX_RETRIES;

      const config = openaiConfig();
      expect(config.apiKey).toBe('');
      expect(config.model).toBe('gpt-4o');
      expect(config.embeddingModel).toBe('text-embedding-3-small');
      expect(config.timeout).toBe(180000);
      expect(config.maxRetries).toBe(3);
    });

    it('should use custom values', () => {
      process.env.OPENAI_API_KEY = 'sk-test';
      process.env.OPENAI_MODEL = 'gpt-4';
      process.env.OPENAI_EMBEDDING_MODEL = 'text-embedding-ada-002';
      process.env.OPENAI_TIMEOUT = '60000';
      process.env.OPENAI_MAX_RETRIES = '5';

      const config = openaiConfig();
      expect(config.apiKey).toBe('sk-test');
      expect(config.model).toBe('gpt-4');
      expect(config.embeddingModel).toBe('text-embedding-ada-002');
      expect(config.timeout).toBe(60000);
      expect(config.maxRetries).toBe(5);
    });
  });

  describe('asaasConfig', () => {
    it('should return defaults when env vars are not set', () => {
      delete process.env.ASAAS_BASE_URL;
      delete process.env.ASAAS_API_KEY;
      delete process.env.ASAAS_WEBHOOK_SECRET;

      const config = asaasConfig();
      expect(config.baseUrl).toBe('https://sandbox.asaas.com/api/v3');
      expect(config.apiKey).toBe('');
      expect(config.webhookSecret).toBe('');
    });

    it('should use custom values', () => {
      process.env.ASAAS_BASE_URL = 'https://api.asaas.com/api/v3';
      process.env.ASAAS_API_KEY = 'asaas-key';
      process.env.ASAAS_WEBHOOK_SECRET = 'webhook-secret';

      const config = asaasConfig();
      expect(config.baseUrl).toBe('https://api.asaas.com/api/v3');
      expect(config.apiKey).toBe('asaas-key');
      expect(config.webhookSecret).toBe('webhook-secret');
    });
  });

  describe('aiConfig', () => {
    it('should return default values when env vars are not set', () => {
      delete process.env.AI_DELEGATION_WAIT_TIMEOUT_MS;
      delete process.env.AI_TOOL_RPC_TIMEOUT_MS;

      const config = aiConfig();
      expect(config.delegationWaitTimeoutMs).toBe(8000);
      expect(config.toolRpcTimeoutMs).toBe(2000);
    });

    it('should parse custom AI timeout values', () => {
      process.env.AI_DELEGATION_WAIT_TIMEOUT_MS = '12000';
      process.env.AI_TOOL_RPC_TIMEOUT_MS = '3500';

      const config = aiConfig();
      expect(config.delegationWaitTimeoutMs).toBe(12000);
      expect(config.toolRpcTimeoutMs).toBe(3500);
    });
  });
});
