import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import { ValidationPipe } from '@nestjs/common';
import { UazapiContactsController } from './uazapi-contacts.controller';
import { UazapiClient } from '../providers/uazapi/uazapi.client';
import { InternalApiKeyGuard } from '../../realtime/guards/internal-api-key.guard';

describe('UazapiContactsController', () => {
  let controller: UazapiContactsController;
  let client: jest.Mocked<UazapiClient>;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      controllers: [UazapiContactsController],
      providers: [
        {
          provide: UazapiClient,
          useValue: {
            listContacts: jest.fn(),
            addContact: jest.fn(),
            removeContact: jest.fn(),
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

    controller = module.get<UazapiContactsController>(UazapiContactsController);
    client = module.get(UazapiClient);
  });

  it('should be defined', () => {
    expect(controller).toBeDefined();
  });

  describe('guards and pipes', () => {
    it('should have InternalApiKeyGuard applied at controller level', () => {
      const guards = Reflect.getMetadata(
        '__guards__',
        UazapiContactsController,
      );
      expect(guards).toBeDefined();
      expect(guards.length).toBeGreaterThanOrEqual(1);

      const guardInstances = guards.map(
        // eslint-disable-next-line @typescript-eslint/no-unsafe-return
        (g: any) => (typeof g === 'function' ? g : g.constructor),
      );
      expect(guardInstances).toContain(InternalApiKeyGuard);
    });

    it('should have ValidationPipe applied at controller level', () => {
      const pipes = Reflect.getMetadata('__pipes__', UazapiContactsController);
      expect(pipes).toBeDefined();
      expect(pipes.length).toBeGreaterThanOrEqual(1);
      const hasPipe = pipes.some((p: any) => p instanceof ValidationPipe);
      expect(hasPipe).toBe(true);
    });
  });

  describe('list', () => {
    it('should list contacts', async () => {
      const contacts = [
        { name: 'John', phone: '5511999999999' },
        { name: 'Jane', phone: '5511888888888' },
      ];
      client.listContacts.mockResolvedValue(contacts);

      const result = await controller.list('inst-token');

      expect(client.listContacts).toHaveBeenCalledWith('inst-token');
      expect(result).toEqual(contacts);
    });
  });

  describe('listPaginated', () => {
    it('should list contacts with pagination body', async () => {
      const contacts = [{ name: 'John', phone: '5511999999999' }];
      client.listContacts.mockResolvedValue(contacts);

      const result = await controller.listPaginated('inst-token', {
        page: 1,
        pageSize: 10,
      });

      expect(client.listContacts).toHaveBeenCalledWith('inst-token', {
        page: 1,
        pageSize: 10,
      });
      expect(result).toEqual(contacts);
    });
  });

  describe('add', () => {
    it('should add a contact', async () => {
      client.addContact.mockResolvedValue({ success: true });

      const result = await controller.add('inst-token', {
        name: 'New Contact',
        phone: '5511777777777',
      });

      expect(client.addContact).toHaveBeenCalledWith('inst-token', {
        name: 'New Contact',
        phone: '5511777777777',
      });
      expect(result).toEqual({ success: true });
    });
  });

  describe('remove', () => {
    it('should remove a contact', async () => {
      client.removeContact.mockResolvedValue({ success: true });

      const result = await controller.remove('inst-token', {
        phone: '5511777777777',
      });

      expect(client.removeContact).toHaveBeenCalledWith('inst-token', {
        phone: '5511777777777',
      });
      expect(result).toEqual({ success: true });
    });
  });
});
