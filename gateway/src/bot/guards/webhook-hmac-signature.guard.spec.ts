import { ExecutionContext } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { WebhookHmacSignatureGuard } from './webhook-hmac-signature.guard';

describe('WebhookHmacSignatureGuard', () => {
  let guard: WebhookHmacSignatureGuard;
  let configService: jest.Mocked<ConfigService>;

  beforeEach(() => {
    configService = {
      get: jest.fn(),
    } as unknown as jest.Mocked<ConfigService>;

    guard = new WebhookHmacSignatureGuard(configService);
  });

  function createMockContext(
    headers: Record<string, string | undefined> = {},
  ): ExecutionContext {
    return {
      switchToHttp: () => ({
        getRequest: () => ({ headers }),
      }),
    } as unknown as ExecutionContext;
  }

  it('should return true when secret token matches expected secret', () => {
    configService.get.mockReturnValue('my-secret-token-123');
    const ctx = createMockContext({
      'x-telegram-bot-api-secret-token': 'my-secret-token-123',
    });

    expect(guard.canActivate(ctx)).toBe(true);
  });

  it('should return false when secret token does not match', () => {
    configService.get.mockReturnValue('expected-secret');
    const ctx = createMockContext({
      'x-telegram-bot-api-secret-token': 'wrong-secret',
    });

    expect(guard.canActivate(ctx)).toBe(false);
  });

  it('should return false when secret token header is missing', () => {
    configService.get.mockReturnValue('expected-secret');
    const ctx = createMockContext({});

    expect(guard.canActivate(ctx)).toBe(false);
  });

  it('should return false when expected secret is not configured', () => {
    configService.get.mockReturnValue(undefined);
    const ctx = createMockContext({
      'x-telegram-bot-api-secret-token': 'some-token',
    });

    expect(guard.canActivate(ctx)).toBe(false);
  });

  it('should return false when both are missing', () => {
    configService.get.mockReturnValue(undefined);
    const ctx = createMockContext({});

    expect(guard.canActivate(ctx)).toBe(false);
  });

  it('should return false for tokens of different lengths (timing-safe)', () => {
    configService.get.mockReturnValue('short');
    const ctx = createMockContext({
      'x-telegram-bot-api-secret-token': 'a-much-longer-token-value',
    });

    expect(guard.canActivate(ctx)).toBe(false);
  });

  it('should handle empty strings as invalid', () => {
    configService.get.mockReturnValue('');
    const ctx = createMockContext({
      'x-telegram-bot-api-secret-token': '',
    });

    // Both empty strings → falsy → guard returns false
    expect(guard.canActivate(ctx)).toBe(false);
  });
});
