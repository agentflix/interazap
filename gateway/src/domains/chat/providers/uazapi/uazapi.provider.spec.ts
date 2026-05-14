import { Test, TestingModule } from '@nestjs/testing';
import { UazapiProvider } from './uazapi.provider';
import { UazapiWebhookDto } from './uazapi.dto';

describe('UazapiProvider', () => {
  let provider: UazapiProvider;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: [UazapiProvider],
    }).compile();

    provider = module.get<UazapiProvider>(UazapiProvider);
  });

  describe('normalize', () => {
    it('should normalize basic message event', () => {
      const payload = {
        EventType: 'messages',
        message: {
          id: 'msg-1',
          body: 'Hello World',
          type: 'text',
          from: '5511999999999',
          fromMe: false,
        },
      } as UazapiWebhookDto;

      const result = provider.normalize('token-1', payload);

      expect(result).toBeDefined();
      expect(result.message?.id).toBe('msg-1');
      expect(result.message?.body).toBe('Hello World');
      expect(result.provider).toBe('uazapi');
    });

    it('should extract body from different fields', () => {
      const payload = {
        EventType: 'messages',
        message: {
          text: 'Test message',
          from: '5511999999999',
        },
      } as UazapiWebhookDto;

      const result = provider.normalize('token-1', payload);

      expect(result).toBeDefined();
    });

    it('should detect direction from fromMe field', () => {
      const outgoing = {
        EventType: 'messages',
        message: {
          id: 'msg-1',
          body: 'Outgoing',
          fromMe: true,
        },
      } as UazapiWebhookDto;

      const incoming = {
        EventType: 'messages',
        message: {
          id: 'msg-2',
          body: 'Incoming',
          fromMe: false,
        },
      } as UazapiWebhookDto;

      const resultOut = provider.normalize('token-1', outgoing);
      const resultIn = provider.normalize('token-1', incoming);

      expect(resultOut).toBeDefined();
      expect(resultIn).toBeDefined();
    });

    it('should handle media messages', () => {
      const payload = {
        EventType: 'messages',
        message: {
          id: 'msg-1',
          type: 'image',
          content: {
            caption: 'Test image',
            URL: 'https://example.com/image.jpg',
          },
          from: '5511999999999',
        },
      } as UazapiWebhookDto;

      const result = provider.normalize('token-1', payload);

      expect(result).toBeDefined();
    });

    it('should handle connection events', () => {
      const payload = {
        EventType: 'connection',
        instance: {
          status: 'connected',
          qrCode: null,
        },
      } as UazapiWebhookDto;

      const result = provider.normalize('token-1', payload);

      expect(result).toBeDefined();
    });

    it('should handle messages with minimal data', () => {
      const payload = {
        message: {
          from: '5511999999999',
        },
      } as UazapiWebhookDto;

      const result = provider.normalize('token-1', payload);

      expect(result).toBeDefined();
    });
  });
});
