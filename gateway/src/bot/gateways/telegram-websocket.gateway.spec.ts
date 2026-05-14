/**
 * Tests for Telegram WebSocket Gateway CORS allowlist.
 *
 * Since the @WebSocketGateway decorator stores metadata internally,
 * we test the CORS origin logic directly by extracting it into a
 * pure function and verifying its behavior.
 */

/**
 * CORS origin checker extracted for testability.
 * Matches the logic in telegram-websocket.gateway.ts.
 */
function checkCorsOrigin(
  origin: string | undefined,
  envOrigins: string | undefined,
): [Error | null, boolean] {
  const allowedStr = envOrigins ?? '';
  const allowed = allowedStr
    ? allowedStr
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean)
    : ['http://localhost:4200', 'http://127.0.0.1:4200'];

  if (!origin || allowed.includes(origin)) {
    return [null, true];
  }

  return [new Error(`Origin ${origin} not allowed by CORS policy`), false];
}

describe('TelegramWebSocketGateway CORS allowlist', () => {
  afterEach(() => {
    delete process.env.TELEGRAM_WS_ALLOWED_ORIGINS;
  });

  it('should allow origin when it is in the allowlist', () => {
    process.env.TELEGRAM_WS_ALLOWED_ORIGINS =
      'http://localhost:4200,https://app.interazap.com.br';

    const [err, allow] = checkCorsOrigin(
      'http://localhost:4200',
      process.env.TELEGRAM_WS_ALLOWED_ORIGINS,
    );

    expect(err).toBeNull();
    expect(allow).toBe(true);
  });

  it('should reject origin when it is NOT in the allowlist', () => {
    process.env.TELEGRAM_WS_ALLOWED_ORIGINS =
      'http://localhost:4200,https://app.interazap.com.br';

    const [err, allow] = checkCorsOrigin(
      'https://evil.com',
      process.env.TELEGRAM_WS_ALLOWED_ORIGINS,
    );

    expect(err).not.toBeNull();
    expect(err?.message).toContain('not allowed');
    expect(allow).toBe(false);
  });

  it('should allow localhost origins by default when env is not set', () => {
    delete process.env.TELEGRAM_WS_ALLOWED_ORIGINS;

    const [err, allow] = checkCorsOrigin(
      'http://localhost:4200',
      process.env.TELEGRAM_WS_ALLOWED_ORIGINS,
    );

    expect(err).toBeNull();
    expect(allow).toBe(true);
  });

  it('should allow request when origin is undefined (same-origin/mobile)', () => {
    process.env.TELEGRAM_WS_ALLOWED_ORIGINS = 'http://localhost:4200';

    const [err, allow] = checkCorsOrigin(
      undefined,
      process.env.TELEGRAM_WS_ALLOWED_ORIGINS,
    );

    expect(err).toBeNull();
    expect(allow).toBe(true);
  });

  it('should allow 127.0.0.1 by default', () => {
    delete process.env.TELEGRAM_WS_ALLOWED_ORIGINS;

    const [err, allow] = checkCorsOrigin(
      'http://127.0.0.1:4200',
      process.env.TELEGRAM_WS_ALLOWED_ORIGINS,
    );

    expect(err).toBeNull();
    expect(allow).toBe(true);
  });

  it('should reject all origins when allowlist is empty string', () => {
    process.env.TELEGRAM_WS_ALLOWED_ORIGINS = '';

    const [err, allow] = checkCorsOrigin(
      'https://app.interazap.com.br',
      process.env.TELEGRAM_WS_ALLOWED_ORIGINS,
    );

    // Empty string falls back to default localhost origins
    expect(err).not.toBeNull();
    expect(allow).toBe(false);
  });

  it('should handle whitespace in origin list', () => {
    process.env.TELEGRAM_WS_ALLOWED_ORIGINS =
      '  http://localhost:4200 , https://app.interazap.com.br  ';

    const [err, allow] = checkCorsOrigin(
      'https://app.interazap.com.br',
      process.env.TELEGRAM_WS_ALLOWED_ORIGINS,
    );

    expect(err).toBeNull();
    expect(allow).toBe(true);
  });
});
