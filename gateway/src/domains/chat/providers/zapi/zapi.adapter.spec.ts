import { TestingModule } from '@nestjs/testing';
import { ZapiAdapter } from './zapi.adapter';
import { ZapiClient } from './zapi.client';
import { ZapiNormalizer } from './zapi.normalizer';
import { buildTestingModule } from '../../../../test-utils/testing-module.util';

describe('ZapiAdapter', () => {
  let adapter: ZapiAdapter;
  let mockClient: jest.Mocked<ZapiClient>;
  let mockNormalizer: jest.Mocked<ZapiNormalizer>;

  beforeEach(async () => {
    mockClient = {
      sendText: jest.fn(),
      sendImage: jest.fn(),
      sendVideo: jest.fn(),
      sendAudio: jest.fn(),
      sendDocument: jest.fn(),
      getStatus: jest.fn(),
      getQrCode: jest.fn(),
      disconnect: jest.fn(),
    } as unknown as jest.Mocked<ZapiClient>;

    mockNormalizer = {
      normalize: jest.fn(),
    } as unknown as jest.Mocked<ZapiNormalizer>;

    const module: TestingModule = await buildTestingModule({
      providers: [
        ZapiAdapter,
        { provide: ZapiClient, useValue: mockClient },
        { provide: ZapiNormalizer, useValue: mockNormalizer },
      ],
    });

    adapter = module.get<ZapiAdapter>(ZapiAdapter);
  });

  it('should have correct name', () => {
    expect(adapter.name).toBe('zapi');
  });

  describe('sendText', () => {
    it('should send text message successfully', async () => {
      mockClient.sendText.mockResolvedValue({ zapiMessageId: 'msg-123' });

      const result = await adapter.sendText('instance-1:token-1', {
        to: '5511999999999',
        text: 'Hello World',
      });

      expect(result.success).toBe(true);
      expect(result.messageId).toBe('msg-123');
      expect(mockClient.sendText).toHaveBeenCalledWith(
        'instance-1',
        'token-1',
        { phone: '5511999999999', message: 'Hello World' },
      );
    });

    it('should handle send text error', async () => {
      mockClient.sendText.mockRejectedValue(new Error('Network error'));

      const result = await adapter.sendText('instance-1:token-1', {
        to: '5511999999999',
        text: 'Hello',
      });

      expect(result.success).toBe(false);
      expect(result.error).toBe('Network error');
    });

    it('should handle different response formats', async () => {
      mockClient.sendText.mockResolvedValue({ messageId: 'msg-456' });

      const result = await adapter.sendText('instance-1:token-1', {
        to: '5511999999999',
        text: 'Hello',
      });

      expect(result.messageId).toBe('msg-456');
    });
  });

  describe('sendMedia', () => {
    it('should send image successfully', async () => {
      mockClient.sendImage.mockResolvedValue({ zapiMessageId: 'img-123' });

      const result = await adapter.sendMedia('instance-1:token-1', {
        to: '5511999999999',
        type: 'image',
        mediaUrl: 'https://example.com/image.jpg',
        caption: 'My image',
      });

      expect(result.success).toBe(true);
      expect(result.messageId).toBe('img-123');
    });

    it('should send video successfully', async () => {
      mockClient.sendVideo.mockResolvedValue({ messageId: 'vid-123' });

      const result = await adapter.sendMedia('instance-1:token-1', {
        to: '5511999999999',
        type: 'video',
        mediaUrl: 'https://example.com/video.mp4',
      });

      expect(result.success).toBe(true);
      expect(result.messageId).toBe('vid-123');
    });

    it('should send audio successfully', async () => {
      mockClient.sendAudio.mockResolvedValue({ id: 'aud-123' });

      const result = await adapter.sendMedia('instance-1:token-1', {
        to: '5511999999999',
        type: 'audio',
        mediaUrl: 'https://example.com/audio.mp3',
      });

      expect(result.success).toBe(true);
      expect(result.messageId).toBe('aud-123');
    });

    it('should send document successfully', async () => {
      mockClient.sendDocument.mockResolvedValue({ zapiMessageId: 'doc-123' });

      const result = await adapter.sendMedia('instance-1:token-1', {
        to: '5511999999999',
        type: 'document',
        mediaUrl: 'https://example.com/file.pdf',
        fileName: 'file.pdf',
      });

      expect(result.success).toBe(true);
    });

    it('should return error for unsupported media type', async () => {
      const result = await adapter.sendMedia('instance-1:token-1', {
        to: '5511999999999',
        type: 'sticker' as 'image', // force invalid type
        mediaUrl: 'https://example.com/sticker.webp',
      });

      expect(result.success).toBe(false);
      expect(result.error).toContain('Unsupported media type');
    });
  });

  describe('getStatus', () => {
    it('should return instance status', async () => {
      mockClient.getStatus.mockResolvedValue({
        connected: true,
        session: '5511999999999',
        smartphoneConnected: true,
      });

      const result = await adapter.getStatus('instance-1:token-1');

      expect(result.connected).toBe(true);
      expect(result.loggedIn).toBe(true);
      expect(result.phone).toBe('5511999999999');
    });

    it('should handle status error', async () => {
      mockClient.getStatus.mockRejectedValue(new Error('Connection failed'));

      const result = await adapter.getStatus('instance-1:token-1');

      expect(result.connected).toBe(false);
      expect(result.loggedIn).toBe(false);
    });
  });

  describe('getQrCode', () => {
    it('should return QR code', async () => {
      mockClient.getQrCode.mockResolvedValue('base64-qr-code');

      const result = await adapter.getQrCode('instance-1:token-1');

      expect(result).toBe('base64-qr-code');
    });
  });

  describe('disconnect', () => {
    it('should disconnect instance', async () => {
      mockClient.disconnect.mockResolvedValue();

      await expect(
        adapter.disconnect('instance-1:token-1'),
      ).resolves.not.toThrow();

      expect(mockClient.disconnect).toHaveBeenCalledWith(
        'instance-1',
        'token-1',
      );
    });
  });

  describe('normalizeWebhook', () => {
    it('should delegate to normalizer', () => {
      const mockNormalized = {
        provider: 'zapi' as const,
        eventType: 'message',
        direction: 'inbound' as const,
      };
      mockNormalizer.normalize.mockReturnValue(
        mockNormalized as ReturnType<typeof mockNormalizer.normalize>,
      );

      const rawPayload = { messageId: 'msg-123' };
      const result = adapter.normalizeWebhook(
        'token-1',
        rawPayload,
        'tenant-1',
        'instance-1',
      );

      expect(mockNormalizer.normalize).toHaveBeenCalledWith(
        'token-1',
        rawPayload,
        'tenant-1',
        'instance-1',
      );
      expect(result.provider).toBe('zapi');
    });
  });

  describe('parseInstanceToken', () => {
    it('should return error for invalid token format', async () => {
      const result = await adapter.sendText('invalid-token', {
        to: '123',
        text: 'hi',
      });

      expect(result.success).toBe(false);
      expect(result.error).toContain('Invalid instance token format');
    });
  });
});
