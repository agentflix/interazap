import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import { ValidationPipe } from '@nestjs/common';
import { ChatController } from './chat.controller';
import { UazapiClient } from '../providers/uazapi/uazapi.client';
import { InternalApiKeyGuard } from '../../realtime/guards/internal-api-key.guard';

describe('ChatController', () => {
  let controller: ChatController;
  let client: jest.Mocked<UazapiClient>;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      controllers: [ChatController],
      providers: [
        {
          provide: UazapiClient,
          useValue: {
            markAsRead: jest.fn(),
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

    controller = module.get<ChatController>(ChatController);
    client = module.get(UazapiClient);
  });

  it('should be defined', () => {
    expect(controller).toBeDefined();
  });

  describe('guards and pipes', () => {
    it('should have InternalApiKeyGuard applied at controller level', () => {
      const guards = Reflect.getMetadata('__guards__', ChatController);
      expect(guards).toBeDefined();
      expect(guards.length).toBeGreaterThanOrEqual(1);

      const guardInstances = guards.map(
        // eslint-disable-next-line @typescript-eslint/no-unsafe-return
        (g: any) => (typeof g === 'function' ? g : g.constructor),
      );
      expect(guardInstances).toContain(InternalApiKeyGuard);
    });

    it('should have ValidationPipe applied at controller level', () => {
      const pipes = Reflect.getMetadata('__pipes__', ChatController);
      expect(pipes).toBeDefined();
      expect(pipes.length).toBeGreaterThanOrEqual(1);
      const hasPipe = pipes.some((p: any) => p instanceof ValidationPipe);
      expect(hasPipe).toBe(true);
    });
  });

  describe('markAsRead', () => {
    it('should mark messages as read', async () => {
      client.markAsRead.mockResolvedValue({ success: true });

      const result = await controller.markAsRead('inst-token', {
        number: '5511999999999',
        read: true,
      });

      expect(client.markAsRead).toHaveBeenCalledWith('inst-token', {
        number: '5511999999999',
        read: true,
      });
      expect(result).toEqual({ success: true });
    });
  });
});
