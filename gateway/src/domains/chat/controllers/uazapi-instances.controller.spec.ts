import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import { ValidationPipe } from '@nestjs/common';
import { UazapiInstancesController } from './uazapi-instances.controller';
import { UazapiClient } from '../providers/uazapi/uazapi.client';
import { InternalApiKeyGuard } from '../../realtime/guards/internal-api-key.guard';

describe('UazapiInstancesController', () => {
  let controller: UazapiInstancesController;
  let client: jest.Mocked<UazapiClient>;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      controllers: [UazapiInstancesController],
      providers: [
        {
          provide: UazapiClient,
          useValue: {
            initInstance: jest.fn(),
            listInstances: jest.fn(),
            connectInstance: jest.fn(),
            disconnectInstance: jest.fn(),
            instanceStatus: jest.fn(),
            configureWebhook: jest.fn(),
            deleteInstance: jest.fn(),
            updateProfileImage: jest.fn(),
            updatePresence: jest.fn(),
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

    controller = module.get<UazapiInstancesController>(
      UazapiInstancesController,
    );
    client = module.get(UazapiClient);
  });

  it('should be defined', () => {
    expect(controller).toBeDefined();
  });

  describe('guards and pipes', () => {
    it('should have InternalApiKeyGuard applied at controller level', () => {
      const guards = Reflect.getMetadata(
        '__guards__',
        UazapiInstancesController,
      );
      expect(guards).toBeDefined();
      expect(guards.length).toBeGreaterThanOrEqual(1);
      // eslint-disable-next-line @typescript-eslint/no-unsafe-return
      const guardInstances = guards.map(
        // eslint-disable-next-line @typescript-eslint/no-unsafe-return
        (g: any) => (typeof g === 'function' ? g : g.constructor),
      );
      expect(guardInstances).toContain(InternalApiKeyGuard);
    });

    it('should have ValidationPipe applied at controller level', () => {
      const pipes = Reflect.getMetadata('__pipes__', UazapiInstancesController);
      expect(pipes).toBeDefined();
      expect(pipes.length).toBeGreaterThanOrEqual(1);
      const hasPipe = pipes.some((p: any) => p instanceof ValidationPipe);
      expect(hasPipe).toBe(true);
    });
  });

  describe('initInstance', () => {
    it('should initialize instance', async () => {
      client.initInstance.mockResolvedValue({ token: 'new-token' });

      const result = await controller.initInstance({ name: 'test-instance' });

      expect(client.initInstance).toHaveBeenCalledWith({
        name: 'test-instance',
      });
      expect(result).toEqual({ token: 'new-token' });
    });
  });

  describe('listInstances', () => {
    it('should list all instances', async () => {
      client.listInstances.mockResolvedValue([
        { token: 'inst-1' },
        { token: 'inst-2' },
      ]);

      const result = await controller.listInstances();

      expect(client.listInstances).toHaveBeenCalled();
      expect(result).toHaveLength(2);
    });
  });

  describe('connect', () => {
    it('should connect instance', async () => {
      client.connectInstance.mockResolvedValue({ qrCode: 'base64...' });

      const result = await controller.connect('inst-token', {
        waitQrCode: true,
      });

      expect(client.connectInstance).toHaveBeenCalledWith('inst-token', {
        waitQrCode: true,
      });
      expect(result).toEqual({ qrCode: 'base64...' });
    });
  });

  describe('disconnect', () => {
    it('should disconnect instance', async () => {
      client.disconnectInstance.mockResolvedValue({ success: true });

      const result = await controller.disconnect('inst-token');

      expect(client.disconnectInstance).toHaveBeenCalledWith('inst-token');
      expect(result).toEqual({ success: true });
    });
  });

  describe('configureWebhook', () => {
    it('should configure webhook', async () => {
      client.configureWebhook.mockResolvedValue({ configured: true });

      const result = await controller.configureWebhook('inst-token', {
        url: 'https://example.com/webhook',
        enabled: true,
      });

      expect(client.configureWebhook).toHaveBeenCalledWith('inst-token', {
        url: 'https://example.com/webhook',
        enabled: true,
      });
      expect(result).toEqual({ configured: true });
    });
  });

  describe('status', () => {
    it('should get instance status', async () => {
      client.instanceStatus.mockResolvedValue({
        status: 'connected',
        phone: '5511999999999',
      });

      const result = await controller.status('inst-token');

      expect(client.instanceStatus).toHaveBeenCalledWith('inst-token');
      expect(result).toEqual({ status: 'connected', phone: '5511999999999' });
    });
  });

  describe('delete', () => {
    it('should delete instance', async () => {
      client.deleteInstance.mockResolvedValue({ deleted: true });

      const result = await controller.delete('inst-token');

      expect(client.deleteInstance).toHaveBeenCalledWith('inst-token');
      expect(result).toEqual({ deleted: true });
    });
  });

  describe('updateProfileImage', () => {
    it('should update profile image', async () => {
      client.updateProfileImage.mockResolvedValue({ success: true });

      const result = await controller.updateProfileImage('inst-token', {
        image: 'data:image/png;base64,iVBORw0KGgo=',
      });

      expect(client.updateProfileImage).toHaveBeenCalledWith(
        'inst-token',
        'data:image/png;base64,iVBORw0KGgo=',
      );
      expect(result).toEqual({ success: true });
    });
  });

  describe('updatePresence', () => {
    it('should update presence', async () => {
      client.updatePresence.mockResolvedValue({ success: true });

      const result = await controller.updatePresence('inst-token', {
        presence: 'available',
      });

      expect(client.updatePresence).toHaveBeenCalledWith(
        'inst-token',
        'available',
      );
      expect(result).toEqual({ success: true });
    });
  });
});
