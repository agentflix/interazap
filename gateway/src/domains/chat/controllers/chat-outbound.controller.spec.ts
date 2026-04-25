import { Test, TestingModule } from '@nestjs/testing';
import { BadRequestException, ValidationPipe } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { ChatOutboundController } from './chat-outbound.controller';
import { SendMessageService } from '../outbound/send-message.service';
import { OutboundMessageDto } from '../dto/outbound-message.dto';
import { InternalApiKeyGuard } from '../../realtime/guards/internal-api-key.guard';

describe('ChatOutboundController', () => {
  let controller: ChatOutboundController;
  let sendMessageService: jest.Mocked<SendMessageService>;

  beforeEach(async () => {
    const mockSendMessageService = {
      send: jest.fn(),
    };

    const module: TestingModule = await Test.createTestingModule({
      controllers: [ChatOutboundController],
      providers: [
        {
          provide: SendMessageService,
          useValue: mockSendMessageService,
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

    controller = module.get<ChatOutboundController>(ChatOutboundController);
    sendMessageService = module.get(SendMessageService);
  });

  it('should be defined', () => {
    expect(controller).toBeDefined();
  });

  describe('guards and pipes', () => {
    it('should have InternalApiKeyGuard applied at controller level', () => {
      const guards = Reflect.getMetadata('__guards__', ChatOutboundController);
      expect(guards).toBeDefined();
      expect(guards.length).toBeGreaterThanOrEqual(1);

      const guardInstances = guards.map(
        // eslint-disable-next-line @typescript-eslint/no-unsafe-return
        (g: any) => (typeof g === 'function' ? g : g.constructor),
      );
      expect(guardInstances).toContain(InternalApiKeyGuard);
    });

    it('should have ValidationPipe applied at controller level', () => {
      const pipes = Reflect.getMetadata('__pipes__', ChatOutboundController);
      expect(pipes).toBeDefined();
      expect(pipes.length).toBeGreaterThanOrEqual(1);
      const hasPipe = pipes.some((p: any) => p instanceof ValidationPipe);
      expect(hasPipe).toBe(true);
    });
  });

  describe('send', () => {
    it('should send message successfully', async () => {
      const dto = {
        provider: 'zapi' as const,
        tenantId: 'tenant-1',
        instanceId: 'inst-1',
        instanceToken: 'token-1',
        type: 'text' as const,
        to: '5511999999999',
        text: 'Hello World',
      };

      sendMessageService.send.mockResolvedValue({
        success: true,
        messageId: 'msg-123',
      });

      const result = await controller.send(dto);

      expect(result).toEqual({
        success: true,
        messageId: 'msg-123',
        error: undefined,
      });
      expect(sendMessageService.send).toHaveBeenCalledWith(
        expect.objectContaining({
          tenantId: 'tenant-1',
          instanceId: 'inst-1',
          provider: 'zapi',
        }),
      );
    });

    it('should throw BadRequestException for unsupported provider', async () => {
      const dto = {
        provider: 'unsupported' as unknown as OutboundMessageDto['provider'],
        tenantId: 'tenant-1',
        instanceId: 'inst-1',
        instanceToken: 'token-1',
        type: 'text' as const,
        to: '5511999999999',
      };

      await expect(controller.send(dto)).rejects.toThrow(BadRequestException);
      await expect(controller.send(dto)).rejects.toThrow(
        'Outbound provider not supported',
      );
    });

    it('should handle media messages', async () => {
      const dto = {
        provider: 'zapi' as const,
        tenantId: 'tenant-1',
        instanceId: 'inst-1',
        instanceToken: 'token-1',
        type: 'media' as const,
        to: '5511999999999',
        mediaType: 'image',
        mediaUrl: 'https://example.com/image.jpg',
        caption: 'Test image',
      };

      sendMessageService.send.mockResolvedValue({
        success: true,
        messageId: 'msg-456',
      });

      const result = await controller.send(dto);

      expect(result.success).toBe(true);
      expect(sendMessageService.send).toHaveBeenCalledWith(
        expect.objectContaining({
          type: 'media',
          mediaUrl: 'https://example.com/image.jpg',
        }),
      );
    });

    it('should handle send failures', async () => {
      const dto = {
        provider: 'zapi' as const,
        tenantId: 'tenant-1',
        instanceId: 'inst-1',
        instanceToken: 'token-1',
        type: 'text' as const,
        to: '5511999999999',
        text: 'Hello',
      };

      sendMessageService.send.mockResolvedValue({
        success: false,
        error: 'Network error',
      });

      const result = await controller.send(dto);

      expect(result.success).toBe(false);
      expect(result.error).toBe('Network error');
    });
  });
});
