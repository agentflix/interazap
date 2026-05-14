import { Test, TestingModule } from '@nestjs/testing';
import { BadRequestException, ValidationPipe } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import {
  UazapiMessagesController,
  UazapiPresenceController,
} from './uazapi-messages.controller';
import { UazapiClient } from '../providers/uazapi/uazapi.client';
import { InternalApiKeyGuard } from '../../realtime/guards/internal-api-key.guard';

describe('UazapiMessagesController', () => {
  let controller: UazapiMessagesController;
  let client: jest.Mocked<UazapiClient>;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      controllers: [UazapiMessagesController],
      providers: [
        {
          provide: UazapiClient,
          useValue: {
            sendText: jest.fn(),
            sendFile: jest.fn(),
          },
        },
        {
          provide: ConfigService,
          useValue: {
            get: jest.fn().mockReturnValue('test-api-key'),
          },
        },
        InternalApiKeyGuard,
      ],
    }).compile();

    controller = module.get<UazapiMessagesController>(UazapiMessagesController);
    client = module.get(UazapiClient);
  });

  it('should be defined', () => {
    expect(controller).toBeDefined();
  });

  describe('guards and pipes', () => {
    it('should have InternalApiKeyGuard applied at controller level', () => {
      const guards = Reflect.getMetadata(
        '__guards__',
        UazapiMessagesController,
      );
      expect(guards).toBeDefined();
      expect(guards.length).toBeGreaterThanOrEqual(1);

      const guardInstances = guards.map((g: any) =>
        typeof g === 'function' ? g : g.constructor,
      );
      expect(guardInstances).toContain(InternalApiKeyGuard);
    });

    it('should have ValidationPipe applied at controller level', () => {
      const pipes = Reflect.getMetadata('__pipes__', UazapiMessagesController);
      expect(pipes).toBeDefined();
      expect(pipes.length).toBeGreaterThanOrEqual(1);
      const hasPipe = pipes.some((p: any) => p instanceof ValidationPipe);
      expect(hasPipe).toBe(true);
    });
  });

  describe('sendText', () => {
    it('should send text message', async () => {
      client.sendText.mockResolvedValue({ messageId: 'msg-123' });

      const result = await controller.sendText('inst-token', {
        number: '5511999999999',
        text: 'Hello World',
      });

      expect(client.sendText).toHaveBeenCalledWith('inst-token', {
        number: '5511999999999',
        text: 'Hello World',
      });
      expect(result).toEqual({ messageId: 'msg-123' });
    });
  });

  describe('sendFile', () => {
    it('should send file/media with url mapped to file', async () => {
      client.sendFile.mockResolvedValue({ messageId: 'msg-456' });

      const result = await controller.sendFile('inst-token', {
        number: '5511999999999',
        url: 'https://example.com/image.jpg',
        caption: 'Check this out',
        type: 'image',
      });

      expect(client.sendFile).toHaveBeenCalledWith('inst-token', {
        number: '5511999999999',
        file: 'https://example.com/image.jpg',
        text: 'Check this out',
        type: 'image',
      });
      expect(result).toEqual({ messageId: 'msg-456' });
    });

    it('should keep file field as-is (Uazapi spec)', async () => {
      client.sendFile.mockResolvedValue({ messageId: 'msg-789' });

      const result = await controller.sendFile('inst-token', {
        number: '5511999999999',
        file: 'data:application/pdf;base64,JVBERi0xLjQ=',
        caption: 'PDF document',
        type: 'document',
        fileName: 'contract.pdf',
      });

      expect(client.sendFile).toHaveBeenCalledWith('inst-token', {
        number: '5511999999999',
        file: 'data:application/pdf;base64,JVBERi0xLjQ=',
        text: 'PDF document',
        type: 'document',
        docName: 'contract.pdf',
      });
      expect(result).toEqual({ messageId: 'msg-789' });
    });

    it('should prefer file over url when both are provided', async () => {
      client.sendFile.mockResolvedValue({ messageId: 'msg-100' });

      await controller.sendFile('inst-token', {
        number: '5511999999999',
        file: 'https://example.com/file.pdf',
        url: 'https://example.com/url.pdf',
        type: 'document',
      });

      expect(client.sendFile).toHaveBeenCalledWith(
        'inst-token',
        expect.objectContaining({ file: 'https://example.com/file.pdf' }),
      );
    });

    it('should pass text directly when provided (no caption override)', async () => {
      client.sendFile.mockResolvedValue({ messageId: 'msg-200' });

      await controller.sendFile('inst-token', {
        number: '5511999999999',
        file: 'https://example.com/img.jpg',
        text: 'Direct text',
        caption: 'Should be ignored',
        type: 'image',
      });

      expect(client.sendFile).toHaveBeenCalledWith(
        'inst-token',
        expect.objectContaining({ text: 'Direct text' }),
      );
    });

    it('should pass through replyid and other Uazapi fields', async () => {
      client.sendFile.mockResolvedValue({ messageId: 'msg-300' });

      await controller.sendFile('inst-token', {
        number: '5511999999999',
        file: 'https://example.com/video.mp4',
        type: 'video',
        replyid: '3EB0538DA65A59F6D8A251',
      });

      expect(client.sendFile).toHaveBeenCalledWith(
        'inst-token',
        expect.objectContaining({
          type: 'video',
          replyid: '3EB0538DA65A59F6D8A251',
        }),
      );
    });

    it('should throw when url and file are missing', () => {
      expect(() =>
        controller.sendFile('inst-token', {
          number: '5511999999999',
        }),
      ).toThrow(BadRequestException);
    });
  });

  describe('sendMedia', () => {
    it('should send media with url mapped to file', async () => {
      client.sendFile.mockResolvedValue({ messageId: 'msg-media-1' });

      const result = await controller.sendMedia('inst-token', {
        number: '5511999999999',
        url: 'https://example.com/photo.jpg',
        type: 'image',
      });

      expect(client.sendFile).toHaveBeenCalledWith(
        'inst-token',
        expect.objectContaining({
          file: 'https://example.com/photo.jpg',
          type: 'image',
        }),
      );
      expect(result).toEqual({ messageId: 'msg-media-1' });
    });

    it('should fallback image/webp to document to avoid unsupported image conversion', async () => {
      client.sendFile.mockResolvedValue({ messageId: 'msg-webp' });

      await controller.sendFile('inst-token', {
        number: '5511999999999',
        file: 'data:image/webp;base64,UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoQABAAPpEwJaQAA3AA/vuUAAA=',
        type: 'image',
        fileName: 'file.webp',
      });

      expect(client.sendFile).toHaveBeenCalledWith(
        'inst-token',
        expect.objectContaining({
          type: 'document',
          docName: 'file.webp',
        }),
      );
    });

    it('should fallback image/svg+xml to document when mimetype is provided', async () => {
      client.sendFile.mockResolvedValue({ messageId: 'msg-svg' });

      await controller.sendFile('inst-token', {
        number: '5511999999999',
        file: 'https://example.com/image.svg',
        type: 'image',
        mimetype: 'image/svg+xml',
      });

      expect(client.sendFile).toHaveBeenCalledWith(
        'inst-token',
        expect.objectContaining({
          type: 'document',
        }),
      );
    });
  });
});

describe('UazapiPresenceController', () => {
  let controller: UazapiPresenceController;
  let client: jest.Mocked<UazapiClient>;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      controllers: [UazapiPresenceController],
      providers: [
        {
          provide: UazapiClient,
          useValue: {
            sendPresence: jest.fn(),
            downloadMedia: jest.fn(),
          },
        },
        {
          provide: ConfigService,
          useValue: {
            get: jest.fn().mockReturnValue('test-api-key'),
          },
        },
        InternalApiKeyGuard,
      ],
    }).compile();

    controller = module.get<UazapiPresenceController>(UazapiPresenceController);
    client = module.get(UazapiClient);
  });

  it('should be defined', () => {
    expect(controller).toBeDefined();
  });

  describe('guards and pipes', () => {
    it('should have InternalApiKeyGuard applied at controller level', () => {
      const guards = Reflect.getMetadata(
        '__guards__',
        UazapiPresenceController,
      );
      expect(guards).toBeDefined();
      expect(guards.length).toBeGreaterThanOrEqual(1);

      const guardInstances = guards.map((g: any) =>
        typeof g === 'function' ? g : g.constructor,
      );
      expect(guardInstances).toContain(InternalApiKeyGuard);
    });

    it('should have ValidationPipe applied at controller level', () => {
      const pipes = Reflect.getMetadata('__pipes__', UazapiPresenceController);
      expect(pipes).toBeDefined();
      expect(pipes.length).toBeGreaterThanOrEqual(1);
      const hasPipe = pipes.some((p: any) => p instanceof ValidationPipe);
      expect(hasPipe).toBe(true);
    });
  });

  describe('sendPresence', () => {
    it('should send presence', async () => {
      client.sendPresence.mockResolvedValue({ success: true });

      const result = await controller.sendPresence('inst-token', {
        number: '5511999999999',
        presence: 'composing',
      });

      expect(client.sendPresence).toHaveBeenCalledWith('inst-token', {
        number: '5511999999999',
        presence: 'composing',
      });
      expect(result).toEqual({ success: true });
    });
  });

  describe('downloadMedia', () => {
    it('should download media', async () => {
      client.downloadMedia.mockResolvedValue({
        fileURL: 'https://files.uazapi.com/decrypted.jpg',
        mimetype: 'image/jpeg',
      });

      const result = await controller.downloadMedia('inst-token', {
        id: 'msg-789',
        return_link: true,
      });

      expect(client.downloadMedia).toHaveBeenCalledWith('inst-token', {
        id: 'msg-789',
        return_link: true,
      });
      expect(result).toEqual({
        fileURL: 'https://files.uazapi.com/decrypted.jpg',
        mimetype: 'image/jpeg',
      });
    });
  });
});
