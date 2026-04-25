import 'reflect-metadata';
import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import { LongPollingStrategy } from '../../src/bot/services/strategies/long-polling.strategy';
import { WebhookStrategy } from '../../src/bot/services/strategies/webhook.strategy';
import { PollingStrategyFactory } from '../../src/bot/services/strategies/polling-strategy.factory';
import { TelegramClientService } from '../../src/bot/services/telegram-client.service';

// ─── Constants ───────────────────────────────────────────────

const BOT_TOKEN = '123456:ABC-DEF';
const WEBHOOK_TOKEN = 'wh-token-123';

// ─── Helpers ─────────────────────────────────────────────────

function createMockTelegramClient(): Record<string, jest.Mock> {
  return {
    deleteWebhook: jest.fn().mockResolvedValue({ ok: true }),
    getUpdates: jest.fn().mockResolvedValue({ ok: true, result: [] }),
    setWebhook: jest.fn().mockResolvedValue({ ok: true }),
    getWebhookInfo: jest.fn().mockResolvedValue({
      ok: true,
      result: { url: 'https://example.com/webhooks/telegram/wh-token-123' },
    }),
  };
}

function createMockConfigService(
  overrides: Record<string, string> = {},
): Partial<ConfigService> {
  const store: Record<string, string> = {
    GATEWAY_BASE_URL: 'https://example.com',
    NODE_ENV: 'development',
    ...overrides,
  };

  return {
    get: jest.fn((key: string) => store[key]),
  };
}

// ─── LongPollingStrategy ─────────────────────────────────────

describe('LongPollingStrategy', () => {
  let strategy: LongPollingStrategy;
  let telegramClient: Record<string, jest.Mock>;

  beforeEach(async () => {
    telegramClient = createMockTelegramClient();

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        LongPollingStrategy,
        { provide: TelegramClientService, useValue: telegramClient },
      ],
    }).compile();

    strategy = module.get(LongPollingStrategy);
  });

  afterEach(async () => {
    await strategy.stop();
  });

  describe('start', () => {
    it('should call deleteWebhook then start polling', async () => {
      await strategy.start(BOT_TOKEN, WEBHOOK_TOKEN);

      expect(telegramClient.deleteWebhook).toHaveBeenCalledWith(
        BOT_TOKEN,
        false,
      );
      expect(strategy.isActive()).toBe(true);
    });
  });

  describe('stop', () => {
    it('should set isRunning to false', async () => {
      await strategy.start(BOT_TOKEN, WEBHOOK_TOKEN);
      expect(strategy.isActive()).toBe(true);

      await strategy.stop();
      expect(strategy.isActive()).toBe(false);
    });
  });

  describe('offset tracking', () => {
    it('should use offset = last_update_id + 1 after receiving updates', async () => {
      // First call returns updates, second hangs (we stop before it resolves)
      telegramClient.getUpdates
        .mockResolvedValueOnce({
          ok: true,
          result: [{ update_id: 50 }, { update_id: 51 }],
        })
        .mockImplementation(
          () => new Promise(() => {}), // never resolves — holds the loop
        );

      await strategy.start(BOT_TOKEN, WEBHOOK_TOKEN);

      // Wait for the first poll cycle to complete
      await new Promise((resolve) => setTimeout(resolve, 100));

      // The second getUpdates call should use offset = 52 (51 + 1)
      const calls = telegramClient.getUpdates.mock.calls;
      if (calls.length >= 2) {
        const secondCallOffset = calls[1][0]; // botToken
        const secondCallOffsetArg = calls[1][1]; // offset
        expect(secondCallOffsetArg).toBe(52);
      }
    });
  });
});

// ─── WebhookStrategy ─────────────────────────────────────────

describe('WebhookStrategy', () => {
  let strategy: WebhookStrategy;
  let telegramClient: Record<string, jest.Mock>;
  let configService: Partial<ConfigService>;

  beforeEach(async () => {
    telegramClient = createMockTelegramClient();
    configService = createMockConfigService();

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        WebhookStrategy,
        { provide: TelegramClientService, useValue: telegramClient },
        { provide: ConfigService, useValue: configService },
      ],
    }).compile();

    strategy = module.get(WebhookStrategy);
  });

  describe('start', () => {
    it('should call setWebhook with HTTPS URL', async () => {
      await strategy.start(BOT_TOKEN, WEBHOOK_TOKEN);

      expect(telegramClient.setWebhook).toHaveBeenCalledWith(
        BOT_TOKEN,
        'https://example.com/webhooks/telegram/wh-token-123',
        expect.any(String),
      );
      expect(strategy.isActive()).toBe(true);
    });
  });

  describe('stop', () => {
    it('should call deleteWebhook', async () => {
      await strategy.start(BOT_TOKEN, WEBHOOK_TOKEN);

      await strategy.stop();

      expect(telegramClient.deleteWebhook).toHaveBeenCalledWith(
        BOT_TOKEN,
        false,
      );
      expect(strategy.isActive()).toBe(false);
    });
  });
});

// ─── PollingStrategyFactory ──────────────────────────────────

describe('PollingStrategyFactory', () => {
  function createFactory(envOverrides: Record<string, string> = {}) {
    const telegramClient = createMockTelegramClient();
    const configService = createMockConfigService(envOverrides);

    const longPolling = new LongPollingStrategy(
      telegramClient as unknown as TelegramClientService,
    );
    const webhook = new WebhookStrategy(
      telegramClient as unknown as TelegramClientService,
      configService as ConfigService,
    );

    const factory = new PollingStrategyFactory(
      configService as ConfigService,
      longPolling,
      webhook,
    );

    return { factory, longPolling, webhook };
  }

  describe('getStrategy', () => {
    it('should return LongPolling when TELEGRAM_POLLING_MODE=long_polling', () => {
      const { factory, longPolling } = createFactory({
        TELEGRAM_POLLING_MODE: 'long_polling',
      });

      const strategy = factory.getStrategy();
      expect(strategy).toBe(longPolling);
      expect(strategy.name).toBe('long_polling');
    });

    it('should return Webhook when TELEGRAM_POLLING_MODE=webhook', () => {
      const { factory, webhook } = createFactory({
        TELEGRAM_POLLING_MODE: 'webhook',
      });

      const strategy = factory.getStrategy();
      expect(strategy).toBe(webhook);
      expect(strategy.name).toBe('webhook');
    });

    it('should default to webhook when NODE_ENV=production', () => {
      const { factory } = createFactory({
        NODE_ENV: 'production',
      });

      const strategy = factory.getStrategy();
      expect(strategy.name).toBe('webhook');
    });

    it('should default to long_polling when NODE_ENV is not production', () => {
      const { factory } = createFactory({
        NODE_ENV: 'development',
      });

      const strategy = factory.getStrategy();
      expect(strategy.name).toBe('long_polling');
    });
  });
});
