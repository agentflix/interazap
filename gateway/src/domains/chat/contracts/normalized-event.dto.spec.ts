import 'reflect-metadata';
import { validate } from 'class-validator';
import { plainToInstance } from 'class-transformer';
import {
  MessagePayloadDto,
  StatusPayloadDto,
  ConnectionPayloadDto,
  NormalizedWebhookEventDto,
} from './normalized-event.dto';

describe('MessagePayloadDto', () => {
  const validPayload = {
    id: 'msg-123',
    from: '5511999999999',
    to: '5511888888888',
    type: 'text',
    timestamp: '2026-02-01T10:00:00.000Z',
  };

  it('should validate a valid text message', async () => {
    const dto = plainToInstance(MessagePayloadDto, {
      ...validPayload,
      text: 'Hello, world!',
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should validate all message types', async () => {
    const types = [
      'text',
      'image',
      'video',
      'audio',
      'document',
      'sticker',
      'location',
      'contact',
    ] as const;
    for (const type of types) {
      const dto = plainToInstance(MessagePayloadDto, { ...validPayload, type });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
    }
  });

  it('should accept optional media fields', async () => {
    const dto = plainToInstance(MessagePayloadDto, {
      ...validPayload,
      type: 'image',
      mediaUrl: 'https://example.com/image.jpg',
      mimeType: 'image/jpeg',
      caption: 'My image',
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should accept optional document fields', async () => {
    const dto = plainToInstance(MessagePayloadDto, {
      ...validPayload,
      type: 'document',
      mediaUrl: 'https://example.com/file.pdf',
      mimeType: 'application/pdf',
      fileName: 'report.pdf',
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should accept optional flags', async () => {
    const dto = plainToInstance(MessagePayloadDto, {
      ...validPayload,
      isFromMe: true,
      isGroup: false,
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should accept optional quotedMessageId', async () => {
    const dto = plainToInstance(MessagePayloadDto, {
      ...validPayload,
      quotedMessageId: 'quoted-msg-456',
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should fail without id', async () => {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { id: _id, ...payload } = validPayload;
    const dto = plainToInstance(MessagePayloadDto, payload);
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without from', async () => {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { from: _from, ...payload } = validPayload;
    const dto = plainToInstance(MessagePayloadDto, payload);
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without to', async () => {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { to: _to, ...payload } = validPayload;
    const dto = plainToInstance(MessagePayloadDto, payload);
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail with invalid type', async () => {
    const dto = plainToInstance(MessagePayloadDto, {
      ...validPayload,
      type: 'invalid-type',
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without timestamp', async () => {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { timestamp: _timestamp, ...payload } = validPayload;
    const dto = plainToInstance(MessagePayloadDto, payload);
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });
});

describe('StatusPayloadDto', () => {
  const validPayload = {
    messageId: 'msg-123',
    status: 'sent',
    timestamp: '2026-02-01T10:00:00.000Z',
  };

  it('should validate a valid status', async () => {
    const dto = plainToInstance(StatusPayloadDto, validPayload);
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should validate all status types', async () => {
    const statuses = ['sent', 'delivered', 'read', 'failed'] as const;
    for (const status of statuses) {
      const dto = plainToInstance(StatusPayloadDto, {
        ...validPayload,
        status,
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
    }
  });

  it('should accept optional error for failed status', async () => {
    const dto = plainToInstance(StatusPayloadDto, {
      ...validPayload,
      status: 'failed',
      error: 'Message delivery failed',
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should fail without messageId', async () => {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { messageId: _messageId, ...payload } = validPayload;
    const dto = plainToInstance(StatusPayloadDto, payload);
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail with invalid status', async () => {
    const dto = plainToInstance(StatusPayloadDto, {
      ...validPayload,
      status: 'invalid-status',
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without timestamp', async () => {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { timestamp: _timestamp, ...payload } = validPayload;
    const dto = plainToInstance(StatusPayloadDto, payload);
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });
});

describe('ConnectionPayloadDto', () => {
  it('should validate a valid connection payload', async () => {
    const dto = plainToInstance(ConnectionPayloadDto, {
      status: 'connected',
      connected: true,
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should accept optional qrCode', async () => {
    const dto = plainToInstance(ConnectionPayloadDto, {
      status: 'qrcode',
      connected: false,
      qrCode: 'base64-qrcode-data',
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should accept optional pairCode', async () => {
    const dto = plainToInstance(ConnectionPayloadDto, {
      status: 'pairing',
      connected: false,
      pairCode: '12345678',
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should fail without status', async () => {
    const dto = plainToInstance(ConnectionPayloadDto, { connected: true });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without connected', async () => {
    const dto = plainToInstance(ConnectionPayloadDto, { status: 'connected' });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });
});

describe('NormalizedWebhookEventDto', () => {
  const validPayload = {
    tenantId: 'tenant-123',
    instanceId: 'instance-456',
    instanceWebhookToken: 'token-789',
    provider: 'uazapi',
    eventType: 'message.received',
    direction: 'inbound',
    rawPayload: {},
    idempotencyKey: 'idem-key-123',
    receivedAt: '2026-02-01T10:00:00.000Z',
  };

  it('should validate a valid event', async () => {
    const dto = plainToInstance(NormalizedWebhookEventDto, validPayload);
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
  });

  it('should validate all directions', async () => {
    const directions = ['inbound', 'outbound', 'status', 'connection'] as const;
    for (const direction of directions) {
      const dto = plainToInstance(NormalizedWebhookEventDto, {
        ...validPayload,
        direction,
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
    }
  });

  it('should validate all providers', async () => {
    const providers = ['uazapi', 'zapi'] as const;
    for (const provider of providers) {
      const dto = plainToInstance(NormalizedWebhookEventDto, {
        ...validPayload,
        provider,
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
    }
  });

  it('should accept message payload', async () => {
    const dto = plainToInstance(NormalizedWebhookEventDto, {
      ...validPayload,
      message: {
        id: 'msg-123',
        from: '5511999999999',
        to: '5511888888888',
        type: 'text',
        text: 'Hello',
        timestamp: '2026-02-01T10:00:00.000Z',
      },
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
    expect(dto.message?.id).toBe('msg-123');
  });

  it('should accept status payload', async () => {
    const dto = plainToInstance(NormalizedWebhookEventDto, {
      ...validPayload,
      direction: 'status',
      status: {
        messageId: 'msg-123',
        status: 'delivered',
        timestamp: '2026-02-01T10:00:00.000Z',
      },
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
    expect(dto.status?.messageId).toBe('msg-123');
  });

  it('should accept connection payload', async () => {
    const dto = plainToInstance(NormalizedWebhookEventDto, {
      ...validPayload,
      direction: 'connection',
      connection: {
        status: 'connected',
        connected: true,
      },
    });
    const errors = await validate(dto);
    expect(errors).toHaveLength(0);
    expect(dto.connection?.status).toBe('connected');
  });

  it('should fail without tenantId', async () => {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { tenantId: _tenantId, ...payload } = validPayload;
    const dto = plainToInstance(NormalizedWebhookEventDto, payload);
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without instanceId', async () => {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { instanceId: _instanceId, ...payload } = validPayload;
    const dto = plainToInstance(NormalizedWebhookEventDto, payload);
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without instanceWebhookToken', async () => {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { instanceWebhookToken: _instanceWebhookToken, ...payload } =
      validPayload;
    const dto = plainToInstance(NormalizedWebhookEventDto, payload);
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without provider', async () => {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { provider: _provider, ...payload } = validPayload;
    const dto = plainToInstance(NormalizedWebhookEventDto, payload);
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail with invalid provider', async () => {
    const dto = plainToInstance(NormalizedWebhookEventDto, {
      ...validPayload,
      provider: 'invalid',
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail with invalid direction', async () => {
    const dto = plainToInstance(NormalizedWebhookEventDto, {
      ...validPayload,
      direction: 'invalid',
    });
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without eventType', async () => {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { eventType: _eventType, ...payload } = validPayload;
    const dto = plainToInstance(NormalizedWebhookEventDto, payload);
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without direction', async () => {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { direction: _direction, ...payload } = validPayload;
    const dto = plainToInstance(NormalizedWebhookEventDto, payload);
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without rawPayload', async () => {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { rawPayload: _rawPayload, ...payload } = validPayload;
    const dto = plainToInstance(NormalizedWebhookEventDto, payload);
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without idempotencyKey', async () => {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { idempotencyKey: _idempotencyKey, ...payload } = validPayload;
    const dto = plainToInstance(NormalizedWebhookEventDto, payload);
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });

  it('should fail without receivedAt', async () => {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { receivedAt: _receivedAt, ...payload } = validPayload;
    const dto = plainToInstance(NormalizedWebhookEventDto, payload);
    const errors = await validate(dto);
    expect(errors.length).toBeGreaterThan(0);
  });
});
