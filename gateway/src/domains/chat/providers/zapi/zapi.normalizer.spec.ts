import { ZapiNormalizer, ZapiWebhookPayload } from './zapi.normalizer';

describe('ZapiNormalizer', () => {
  let normalizer: ZapiNormalizer;

  beforeEach(() => {
    normalizer = new ZapiNormalizer();
  });

  describe('normalize', () => {
    const webhookToken = 'test-webhook-token';
    const tenantId = 'tenant-123';
    const instanceId = 'instance-456';

    it('should normalize text message received', () => {
      const payload: ZapiWebhookPayload = {
        messageId: 'msg-123',
        phone: '5511999999999',
        connectedPhone: '5511888888888',
        fromMe: false,
        isGroup: false,
        text: {
          message: 'Hello World',
        },
        type: 'ReceivedCallback',
      };

      const result = normalizer.normalize(
        webhookToken,
        payload,
        tenantId,
        instanceId,
      );

      expect(result.provider).toBe('zapi');
      expect(result.direction).toBe('inbound');
      expect(result.eventType).toBe('message_received');
      expect(result.message).toBeDefined();
      expect(result.message?.id).toBe('msg-123');
      expect(result.message?.text).toBe('Hello World');
      expect(result.message?.from).toBe('5511999999999');
      expect(result.message?.type).toBe('text');
      expect(result.message?.isFromMe).toBe(false);
    });

    it('should normalize outbound message', () => {
      const payload: ZapiWebhookPayload = {
        messageId: 'msg-456',
        phone: '5511999999999',
        fromMe: true,
        text: {
          message: 'Reply message',
        },
      };

      const result = normalizer.normalize(
        webhookToken,
        payload,
        tenantId,
        instanceId,
      );

      expect(result.direction).toBe('outbound');
      expect(result.message?.isFromMe).toBe(true);
    });

    it('should normalize image message', () => {
      const payload: ZapiWebhookPayload = {
        messageId: 'img-123',
        phone: '5511999999999',
        fromMe: false,
        image: {
          imageUrl: 'https://example.com/image.jpg',
          caption: 'Check this out',
          mimeType: 'image/jpeg',
        },
      };

      const result = normalizer.normalize(
        webhookToken,
        payload,
        tenantId,
        instanceId,
      );

      expect(result.message?.type).toBe('image');
      expect(result.message?.mediaUrl).toBe('https://example.com/image.jpg');
      expect(result.message?.caption).toBe('Check this out');
      expect(result.message?.mimeType).toBe('image/jpeg');
    });

    it('should normalize video message', () => {
      const payload: ZapiWebhookPayload = {
        messageId: 'vid-123',
        phone: '5511999999999',
        fromMe: false,
        video: {
          videoUrl: 'https://example.com/video.mp4',
          caption: 'Video caption',
          mimeType: 'video/mp4',
        },
      };

      const result = normalizer.normalize(
        webhookToken,
        payload,
        tenantId,
        instanceId,
      );

      expect(result.message?.type).toBe('video');
      expect(result.message?.mediaUrl).toBe('https://example.com/video.mp4');
    });

    it('should normalize audio message', () => {
      const payload: ZapiWebhookPayload = {
        messageId: 'aud-123',
        phone: '5511999999999',
        fromMe: false,
        audio: {
          audioUrl: 'https://example.com/audio.ogg',
          mimeType: 'audio/ogg',
          ptt: true,
        },
      };

      const result = normalizer.normalize(
        webhookToken,
        payload,
        tenantId,
        instanceId,
      );

      expect(result.message?.type).toBe('audio');
      expect(result.message?.mediaUrl).toBe('https://example.com/audio.ogg');
    });

    it('should normalize document message', () => {
      const payload: ZapiWebhookPayload = {
        messageId: 'doc-123',
        phone: '5511999999999',
        fromMe: false,
        document: {
          documentUrl: 'https://example.com/file.pdf',
          fileName: 'file.pdf',
          mimeType: 'application/pdf',
        },
      };

      const result = normalizer.normalize(
        webhookToken,
        payload,
        tenantId,
        instanceId,
      );

      expect(result.message?.type).toBe('document');
      expect(result.message?.mediaUrl).toBe('https://example.com/file.pdf');
      expect(result.message?.fileName).toBe('file.pdf');
    });

    it('should normalize sticker message', () => {
      const payload: ZapiWebhookPayload = {
        messageId: 'stk-123',
        phone: '5511999999999',
        fromMe: false,
        sticker: {
          stickerUrl: 'https://example.com/sticker.webp',
          mimeType: 'image/webp',
        },
      };

      const result = normalizer.normalize(
        webhookToken,
        payload,
        tenantId,
        instanceId,
      );

      expect(result.message?.type).toBe('sticker');
    });

    it('should normalize message status update', () => {
      const payload: ZapiWebhookPayload = {
        type: 'MessageStatusCallback',
        ids: ['msg-123', 'msg-456'],
        status: 'READ',
      };

      const result = normalizer.normalize(
        webhookToken,
        payload,
        tenantId,
        instanceId,
      );

      expect(result.direction).toBe('status');
      expect(result.eventType).toBe('message_status');
      expect(result.status).toBeDefined();
      expect(result.status?.messageId).toBe('msg-123');
      expect(result.status?.status).toBe('read');
    });

    it('should normalize delivered status', () => {
      const payload: ZapiWebhookPayload = {
        type: 'MessageStatusCallback',
        ids: ['msg-789'],
        status: 'RECEIVED',
      };

      const result = normalizer.normalize(
        webhookToken,
        payload,
        tenantId,
        instanceId,
      );

      expect(result.status?.status).toBe('delivered');
    });

    it('should normalize connection event - connected', () => {
      const payload: ZapiWebhookPayload = {
        type: 'ConnectedCallback',
        connected: true,
        smartphoneConnected: true,
      };

      const result = normalizer.normalize(
        webhookToken,
        payload,
        tenantId,
        instanceId,
      );

      expect(result.direction).toBe('connection');
      expect(result.eventType).toBe('connected');
      expect(result.connection?.connected).toBe(true);
      expect(result.connection?.status).toBe('connected');
    });

    it('should normalize connection event - disconnected', () => {
      const payload: ZapiWebhookPayload = {
        type: 'DisconnectedCallback',
        connected: false,
      };

      const result = normalizer.normalize(
        webhookToken,
        payload,
        tenantId,
        instanceId,
      );

      expect(result.direction).toBe('connection');
      expect(result.eventType).toBe('disconnected');
      expect(result.connection?.connected).toBe(false);
    });

    it('should generate valid idempotency key', () => {
      const payload: ZapiWebhookPayload = {
        messageId: 'msg-123',
        phone: '5511999999999',
      };

      const result = normalizer.normalize(
        webhookToken,
        payload,
        tenantId,
        instanceId,
      );

      expect(result.idempotencyKey).toMatch(/^idempo:/);
      expect(result.idempotencyKey).toContain('zapi');
    });

    it('should hash long idempotency keys', () => {
      const longPayload: ZapiWebhookPayload = {
        messageId: 'a'.repeat(300),
        phone: '5511999999999',
      };

      const result = normalizer.normalize(
        webhookToken,
        longPayload,
        tenantId,
        instanceId,
      );

      expect(result.idempotencyKey).toMatch(/^idempo_hash:/);
      expect(result.idempotencyKey.length).toBeLessThan(100);
    });

    it('should set receivedAt timestamp', () => {
      const before = new Date();
      const payload: ZapiWebhookPayload = { messageId: 'msg-123' };

      const result = normalizer.normalize(
        webhookToken,
        payload,
        tenantId,
        instanceId,
      );

      const after = new Date();
      expect(result.receivedAt.getTime()).toBeGreaterThanOrEqual(
        before.getTime(),
      );
      expect(result.receivedAt.getTime()).toBeLessThanOrEqual(after.getTime());
    });
  });
});
