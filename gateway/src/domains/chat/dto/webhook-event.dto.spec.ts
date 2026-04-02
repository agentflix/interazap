import 'reflect-metadata';
import { validate } from 'class-validator';
import { plainToInstance } from 'class-transformer';
import { WebhookEventDto } from './webhook-event.dto';

describe('WebhookEventDto', () => {
  describe('validation', () => {
    it('should validate with EventType', async () => {
      const dto = plainToInstance(WebhookEventDto, {
        EventType: 'messages',
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
    });

    it('should validate with event_type', async () => {
      const dto = plainToInstance(WebhookEventDto, {
        event_type: 'messages',
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
    });

    it('should validate with message payload', async () => {
      const dto = plainToInstance(WebhookEventDto, {
        EventType: 'messages',
        message: {
          id: 'msg-123',
          from: '5511999999999',
          to: '5511888888888',
          body: 'Hello',
          type: 'text',
        },
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
    });

    it('should validate with instance payload', async () => {
      const dto = plainToInstance(WebhookEventDto, {
        EventType: 'connection',
        instance: {
          name: 'my-instance',
          status: 'connected',
          token: 'token-123',
        },
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
    });

    it('should validate with qrcode in instance', async () => {
      const dto = plainToInstance(WebhookEventDto, {
        EventType: 'connection',
        instance: {
          name: 'my-instance',
          qrcode: 'base64-qrcode-data',
        },
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
    });

    it('should validate with paircode in instance', async () => {
      const dto = plainToInstance(WebhookEventDto, {
        EventType: 'connection',
        instance: {
          name: 'my-instance',
          paircode: '12345678',
        },
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
    });

    it('should validate with status payload', async () => {
      const dto = plainToInstance(WebhookEventDto, {
        EventType: 'connection',
        status: {
          connected: true,
          loggedIn: true,
          status: 'open',
        },
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
    });

    it('should validate with all optional fields', async () => {
      const dto = plainToInstance(WebhookEventDto, {
        EventType: 'messages',
        direction: 'incoming',
        owner: '5511999999999@s.whatsapp.net',
        BaseUrl: 'https://api.example.com',
        chat: { id: 'chat-123' },
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
    });

    it('should validate empty payload', async () => {
      const dto = plainToInstance(WebhookEventDto, {});
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
    });
  });

  describe('Transform decorators', () => {
    it('should keep explicit event_type', () => {
      const dto = plainToInstance(WebhookEventDto, {
        event_type: 'explicit-type',
        EventType: 'event-type',
      });
      expect(dto.event_type).toBe('explicit-type');
    });

    it('should preserve raw payload', () => {
      const payload = {
        EventType: 'messages',
        message: { id: 'msg-123' },
        custom_field: 'custom_value',
        nested: { data: { deep: true } },
      };
      const dto = plainToInstance(WebhookEventDto, payload);
      expect(dto.raw).toBeDefined();
      expect(dto.raw?.EventType).toBe('messages');
      expect(dto.raw?.custom_field).toBe('custom_value');
      expect((dto.raw?.nested as Record<string, unknown>)?.data).toEqual({
        deep: true,
      });
    });

    it('should handle undefined obj in raw transform', () => {
      const dto = plainToInstance(WebhookEventDto, {});
      // Should not throw, raw might be undefined for empty object
      expect(dto).toBeDefined();
    });
  });

  describe('nested DTOs', () => {
    it('should validate nested message with all fields', async () => {
      const dto = plainToInstance(WebhookEventDto, {
        EventType: 'messages',
        message: {
          id: 'msg-123',
          from: '5511999999999',
          to: '5511888888888',
          body: 'Hello world',
          type: 'text',
        },
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
      expect(dto.message?.id).toBe('msg-123');
      expect(dto.message?.body).toBe('Hello world');
    });

    it('should validate nested instance with all fields', async () => {
      const dto = plainToInstance(WebhookEventDto, {
        EventType: 'connection',
        instance: {
          name: 'test-instance',
          status: 'connected',
          token: 'instance-token',
          qrcode: 'qr-data',
          paircode: '12345678',
        },
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
      expect(dto.instance?.name).toBe('test-instance');
      expect(dto.instance?.token).toBe('instance-token');
    });

    it('should validate nested status with all fields', async () => {
      const dto = plainToInstance(WebhookEventDto, {
        EventType: 'connection',
        status: {
          connected: true,
          loggedIn: false,
          status: 'disconnected',
        },
      });
      const errors = await validate(dto);
      expect(errors).toHaveLength(0);
      expect(dto.status?.connected).toBe(true);
      expect(dto.status?.loggedIn).toBe(false);
    });
  });
});
