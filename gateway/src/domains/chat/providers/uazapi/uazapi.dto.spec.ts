import 'reflect-metadata';
import { validate } from 'class-validator';
import { plainToInstance } from 'class-transformer';
import { UazapiWebhookDto } from './uazapi.dto';

describe('UazapiWebhookDto', () => {
  describe('basic structure', () => {
    it('should create webhook DTO', () => {
      const dto: Partial<UazapiWebhookDto> = {
        EventType: 'messages',
        message: {
          id: 'msg-123',
          from: '5511999999999',
          body: 'Test message',
        },
      };

      expect(dto.EventType).toBe('messages');
      expect(dto.message?.id).toBe('msg-123');
    });

    it('should handle connection events', () => {
      const dto: Partial<UazapiWebhookDto> = {
        EventType: 'connection',
        instance: {
          status: 'connected',
        },
      };

      expect(dto.EventType).toBe('connection');
      expect(dto.instance?.status).toBe('connected');
    });

    it('should handle status updates', () => {
      const dto: Partial<UazapiWebhookDto> = {
        EventType: 'messages_update',
        message: {
          id: 'msg-123',
          ack: 'RECEIVED',
        },
      };

      expect(dto.EventType).toBe('messages_update');
      expect(dto.message?.ack).toBe('RECEIVED');
    });
  });

  describe('validation', () => {
    it('should validate messages event type', async () => {
      const dto = plainToInstance(UazapiWebhookDto, {
        EventType: 'messages',
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
    });

    it('should validate messages_update event type', async () => {
      const dto = plainToInstance(UazapiWebhookDto, {
        EventType: 'messages_update',
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
    });

    it('should validate connection event type', async () => {
      const dto = plainToInstance(UazapiWebhookDto, {
        EventType: 'connection',
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
    });

    it('should fail without EventType', async () => {
      const dto = plainToInstance(UazapiWebhookDto, {});
      const errors = await validate(dto);
      expect(errors.length).toBeGreaterThan(0);
    });
  });

  describe('message payload', () => {
    it('should accept message with all fields', async () => {
      const dto = plainToInstance(UazapiWebhookDto, {
        EventType: 'messages',
        message: {
          id: 'msg-123',
          type: 'text',
          from: '5511999999999@s.whatsapp.net',
          to: '5511888888888@s.whatsapp.net',
          body: 'Hello!',
          timestamp: 1706803200,
        },
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
      expect(dto.message?.id).toBe('msg-123');
      expect(dto.message?.body).toBe('Hello!');
    });

    it('should accept message with string timestamp', async () => {
      const dto = plainToInstance(UazapiWebhookDto, {
        EventType: 'messages',
        message: {
          id: 'msg-123',
          timestamp: '2026-02-01T10:00:00.000Z',
        },
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
      expect(dto.message?.timestamp).toBe('2026-02-01T10:00:00.000Z');
    });

    it('should accept message with extra fields via index signature', async () => {
      const dto = plainToInstance(UazapiWebhookDto, {
        EventType: 'messages',
        message: {
          id: 'msg-123',
          custom_field: 'custom_value',
          another_field: 123,
        },
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
      expect(dto.message?.custom_field).toBe('custom_value');
    });
  });

  describe('optional fields', () => {
    it('should accept chat object', async () => {
      const dto = plainToInstance(UazapiWebhookDto, {
        EventType: 'messages',
        chat: {
          id: 'chat-123',
          name: 'Test Chat',
        },
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
      expect(dto.chat?.id).toBe('chat-123');
    });

    it('should accept instance object', async () => {
      const dto = plainToInstance(UazapiWebhookDto, {
        EventType: 'connection',
        instance: {
          name: 'my-instance',
          status: 'connected',
        },
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
      expect(dto.instance?.name).toBe('my-instance');
    });

    it('should accept instanceName', async () => {
      const dto = plainToInstance(UazapiWebhookDto, {
        EventType: 'messages',
        instanceName: 'test-instance',
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
      expect(dto.instanceName).toBe('test-instance');
    });

    it('should accept BaseUrl', async () => {
      const dto = plainToInstance(UazapiWebhookDto, {
        EventType: 'messages',
        BaseUrl: 'https://api.uazapi.com',
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
      expect(dto.BaseUrl).toBe('https://api.uazapi.com');
    });

    it('should accept owner', async () => {
      const dto = plainToInstance(UazapiWebhookDto, {
        EventType: 'messages',
        owner: '5511999999999@s.whatsapp.net',
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
      expect(dto.owner).toBe('5511999999999@s.whatsapp.net');
    });

    it('should accept token', async () => {
      const dto = plainToInstance(UazapiWebhookDto, {
        EventType: 'messages',
        token: 'webhook-token-abc',
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
      expect(dto.token).toBe('webhook-token-abc');
    });
  });

  describe('index signature', () => {
    it('should accept any additional fields', async () => {
      const dto = plainToInstance(UazapiWebhookDto, {
        EventType: 'messages',
        custom_field_1: 'value1',
        custom_field_2: 123,
        nested_custom: { deep: true },
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
      expect(dto['custom_field_1']).toBe('value1');
      expect(dto['custom_field_2']).toBe(123);
    });
  });

  describe('complete webhook payload', () => {
    it('should validate a complete incoming message webhook', async () => {
      const dto = plainToInstance(UazapiWebhookDto, {
        EventType: 'messages',
        message: {
          id: 'BAE5xxxx',
          type: 'text',
          from: '5511999999999@s.whatsapp.net',
          to: '5511888888888@s.whatsapp.net',
          body: 'Hello, this is a test message!',
          timestamp: 1706803200,
        },
        chat: {
          id: '5511999999999@s.whatsapp.net',
          name: 'John Doe',
        },
        owner: '5511888888888@s.whatsapp.net',
        instanceName: 'my-business',
        BaseUrl: 'https://api.uazapi.com',
        token: 'webhook-token-123',
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
    });

    it('should validate a connection status webhook', async () => {
      const dto = plainToInstance(UazapiWebhookDto, {
        EventType: 'connection',
        instance: {
          name: 'my-business',
          status: 'qrcode',
          qrcode: 'base64-qrcode-data',
        },
        owner: '5511888888888@s.whatsapp.net',
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
    });

    it('should validate a message status update webhook', async () => {
      const dto = plainToInstance(UazapiWebhookDto, {
        EventType: 'messages_update',
        message: {
          id: 'BAE5xxxx',
          status: 'delivered',
        },
        instanceName: 'my-business',
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
    });
  });
});
