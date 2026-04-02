import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import { EventFanoutService } from './event-fanout.service';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { EventsGateway } from '../gateways/events.gateway';
import { GatewayConfigService } from '../../../shared/services/gateway-config.service';

describe('EventFanoutService', () => {
  let service: EventFanoutService;
  let mockRedisService: Partial<RedisService>;
  let mockEventsGateway: Partial<EventsGateway>;
  const mockConfigService = {
    get: jest.fn(),
  };
  let mockPubSubClient: {
    on: jest.Mock;
    subscribe: jest.Mock;
    unsubscribe: jest.Mock;
  };
  let messageHandler: ((channel: string, message: string) => void) | null =
    null;

  beforeEach(async () => {
    mockConfigService.get.mockReset();
    mockConfigService.get.mockReturnValue(undefined);

    mockPubSubClient = {
      on: jest.fn((event: string, handler: (...args: unknown[]) => void) => {
        if (event === 'message') {
          messageHandler = handler as (
            channel: string,
            message: string,
          ) => void;
        }
      }),
      subscribe: jest.fn().mockResolvedValue(undefined),
      unsubscribe: jest.fn().mockResolvedValue(undefined),
    };

    mockRedisService = {
      getClient: jest.fn().mockReturnValue({}),
      getPubSubClient: jest.fn().mockReturnValue(mockPubSubClient),
    };

    mockEventsGateway = {
      emitToRoom: jest.fn(),
    };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        EventFanoutService,
        { provide: ConfigService, useValue: mockConfigService },
        { provide: RedisService, useValue: mockRedisService },
        { provide: EventsGateway, useValue: mockEventsGateway },
        {
          provide: GatewayConfigService,
          useValue: {
            isTestEnvironment: jest.fn().mockReturnValue(true),
            wsEventsChannel: 'ws.events',
          },
        },
      ],
    }).compile();

    service = module.get<EventFanoutService>(EventFanoutService);
  });

  afterEach(() => {
    messageHandler = null;
  });

  describe('onModuleInit', () => {
    it('should subscribe to ws.events channel using pub/sub client', async () => {
      await service.onModuleInit();

      expect(mockRedisService.getPubSubClient).toHaveBeenCalled();
      expect(mockPubSubClient.subscribe).toHaveBeenCalledWith('ws.events');
    });

    it('should register message handler', async () => {
      await service.onModuleInit();

      expect(mockPubSubClient.on).toHaveBeenCalledWith(
        'message',
        expect.any(Function),
      );
      expect(messageHandler).not.toBeNull();
    });
  });

  describe('onModuleDestroy', () => {
    it('should unsubscribe from ws.events channel', async () => {
      await service.onModuleInit();
      await service.onModuleDestroy();

      expect(mockPubSubClient.unsubscribe).toHaveBeenCalledWith('ws.events');
    });
  });

  describe('message handling', () => {
    beforeEach(async () => {
      await service.onModuleInit();
    });

    it('should emit chat.message.new for inbound message events', () => {
      const message = JSON.stringify({
        event: 'chat.inbound_message_received',
        data: {
          tenant_id: 'tenant-123',
          ticket_id: 'ticket-456',
          message: {
            id: 'msg-789',
            content: 'Hello World',
            type: 'text',
          },
        },
      });

      messageHandler?.('ws.events', message);

      expect(mockEventsGateway.emitToRoom).toHaveBeenCalledWith(
        'tenant:tenant-123',
        'chat.message.new',
        expect.objectContaining({
          ticket_id: 'ticket-456',
          message: expect.objectContaining({
            id: 'msg-789',
            content: 'Hello World',
          }),
        }),
      );
    });

    it('should handle events without ticket_id', () => {
      const message = JSON.stringify({
        event: 'chat.inbound_message_received',
        data: {
          tenant_id: 'tenant-123',
          message: {
            id: 'msg-abc',
            content: 'No ticket yet',
          },
        },
      });

      messageHandler?.('ws.events', message);

      expect(mockEventsGateway.emitToRoom).toHaveBeenCalledWith(
        'tenant:tenant-123',
        'chat.message.new',
        expect.objectContaining({
          ticket_id: null,
        }),
      );
    });

    it('should ignore events without tenant_id', () => {
      const message = JSON.stringify({
        event: 'chat.inbound_message_received',
        data: {
          message: { id: 'msg-no-tenant' },
        },
      });

      messageHandler?.('ws.events', message);

      expect(mockEventsGateway.emitToRoom).not.toHaveBeenCalled();
    });

    it('should ignore events without event name', () => {
      const message = JSON.stringify({
        data: { tenant_id: 'tenant-123' },
      });

      messageHandler?.('ws.events', message);

      expect(mockEventsGateway.emitToRoom).not.toHaveBeenCalled();
    });

    it('should handle malformed JSON gracefully', () => {
      expect(() => {
        messageHandler?.('ws.events', 'not-valid-json');
      }).not.toThrow();

      expect(mockEventsGateway.emitToRoom).not.toHaveBeenCalled();
    });

    it('should ignore non-chat events', () => {
      const message = JSON.stringify({
        event: 'some.other.event',
        data: {
          tenant_id: 'tenant-123',
          foo: 'bar',
        },
      });

      messageHandler?.('ws.events', message);

      expect(mockEventsGateway.emitToRoom).not.toHaveBeenCalled();
    });

    it('should fanout envelope event to all valid rooms', () => {
      const message = JSON.stringify({
        event: 'chat.activity',
        tenant_id: 'tenant-123',
        rooms: ['tenant:tenant-123', 'ticket:ticket-1'],
        version: 'v1',
        data: {
          tenant_id: 'tenant-123',
          ticket_id: 'ticket-1',
        },
      });

      messageHandler?.('ws.events', message);

      expect(mockEventsGateway.emitToRoom).toHaveBeenCalledWith(
        'tenant:tenant-123',
        'chat.activity',
        expect.objectContaining({ ticket_id: 'ticket-1' }),
      );
      expect(mockEventsGateway.emitToRoom).toHaveBeenCalledWith(
        'ticket:ticket-1',
        'chat.activity',
        expect.objectContaining({ tenant_id: 'tenant-123' }),
      );
    });

    it('should suppress debug log for chat.activity by default', () => {
      const debugSpy = jest
        .spyOn((service as any).logger, 'debug')
        .mockImplementation(() => undefined);

      const message = JSON.stringify({
        event: 'chat.activity',
        tenant_id: 'tenant-123',
        rooms: ['tenant:tenant-123'],
        data: { tenant_id: 'tenant-123' },
      });

      messageHandler?.('ws.events', message);

      expect(debugSpy).not.toHaveBeenCalledWith(
        'Received ws.events: chat.activity',
      );
    });

    it('should enable debug log for chat.activity when flag is true', async () => {
      mockConfigService.get.mockImplementation((key: string) => {
        if (key === 'REALTIME_DEBUG_CHAT_ACTIVITY') {
          return 'true';
        }
        return undefined;
      });

      const debugModule: TestingModule = await Test.createTestingModule({
        providers: [
          EventFanoutService,
          { provide: ConfigService, useValue: mockConfigService },
          { provide: RedisService, useValue: mockRedisService },
          { provide: EventsGateway, useValue: mockEventsGateway },
          {
            provide: GatewayConfigService,
            useValue: {
              isTestEnvironment: jest.fn().mockReturnValue(true),
              wsEventsChannel: 'ws.events',
            },
          },
        ],
      }).compile();
      const debugService =
        debugModule.get<EventFanoutService>(EventFanoutService);
      await debugService.onModuleInit();

      const debugSpy = jest
        .spyOn((debugService as any).logger, 'debug')
        .mockImplementation(() => undefined);

      const message = JSON.stringify({
        event: 'chat.activity',
        tenant_id: 'tenant-123',
        rooms: ['tenant:tenant-123'],
        data: { tenant_id: 'tenant-123' },
      });

      messageHandler?.('ws.events', message);

      expect(debugSpy).toHaveBeenCalledWith(
        'Received ws.events: chat.activity',
      );
    });

    it('should reject envelope with cross-tenant room', () => {
      const message = JSON.stringify({
        event: 'chat.activity',
        tenant_id: 'tenant-123',
        rooms: ['tenant:tenant-999'],
        version: 'v1',
        data: {
          tenant_id: 'tenant-123',
        },
      });

      messageHandler?.('ws.events', message);

      expect(mockEventsGateway.emitToRoom).not.toHaveBeenCalled();
    });

    it('should emit ai.run.* events to tenant and run rooms', () => {
      const message = JSON.stringify({
        event: 'ai.run.started',
        data: {
          tenant_id: 'tenant-ai',
          run_id: 'run-123',
          status: 'running',
        },
      });

      messageHandler?.('ws.events', message);

      expect(mockEventsGateway.emitToRoom).toHaveBeenCalledWith(
        'tenant:tenant-ai',
        'ai.run.started',
        expect.objectContaining({ run_id: 'run-123' }),
      );
      expect(mockEventsGateway.emitToRoom).toHaveBeenCalledWith(
        'run:run-123',
        'ai.run.started',
        expect.objectContaining({ tenant_id: 'tenant-ai' }),
      );
    });

    it('should emit ticket.sentiment_updated to tenant room', () => {
      const message = JSON.stringify({
        event: 'ticket.sentiment_updated',
        data: {
          tenant_id: 'tenant-77',
          ticket_id: 'ticket-1',
          sentiment: 'critical',
          sentiment_score: 91,
        },
      });

      messageHandler?.('ws.events', message);

      expect(mockEventsGateway.emitToRoom).toHaveBeenCalledWith(
        'tenant:tenant-77',
        'ticket.sentiment_updated',
        expect.objectContaining({
          ticket_id: 'ticket-1',
          sentiment: 'critical',
          sentiment_score: 91,
        }),
      );
    });

    it('should ignore ticket.sentiment_updated without tenant_id', () => {
      const message = JSON.stringify({
        event: 'ticket.sentiment_updated',
        data: {
          ticket_id: 'ticket-1',
          sentiment: 'critical',
          sentiment_score: 91,
        },
      });

      messageHandler?.('ws.events', message);

      expect(mockEventsGateway.emitToRoom).not.toHaveBeenCalled();
    });

    it('should ignore ai.run.* event without tenant_id', () => {
      const message = JSON.stringify({
        event: 'ai.run.completed',
        data: {
          run_id: 'run-123',
        },
      });

      messageHandler?.('ws.events', message);

      expect(mockEventsGateway.emitToRoom).not.toHaveBeenCalled();
    });

    it('should handle events with payload structure', () => {
      const message = JSON.stringify({
        event: 'chat.inbound_message_received',
        data: {
          tenant_id: 'tenant-xyz',
          payload: {
            message: {
              id: 'msg-payload',
              content: 'From payload',
            },
          },
        },
      });

      messageHandler?.('ws.events', message);

      expect(mockEventsGateway.emitToRoom).toHaveBeenCalledWith(
        'tenant:tenant-xyz',
        'chat.message.new',
        expect.objectContaining({
          message: expect.objectContaining({
            id: 'msg-payload',
          }),
        }),
      );
    });

    it('should ignore messages from other channels', () => {
      messageHandler?.('other.channel', JSON.stringify({ event: 'test' }));

      expect(mockEventsGateway.emitToRoom).not.toHaveBeenCalled();
    });

    it('should ignore invalid payload shape (non-object)', () => {
      messageHandler?.('ws.events', JSON.stringify('string-payload'));

      expect(mockEventsGateway.emitToRoom).not.toHaveBeenCalled();
    });

    it('should ignore message without message data', () => {
      const message = JSON.stringify({
        event: 'chat.inbound_message_received',
        data: {
          tenant_id: 'tenant-123',
        },
      });

      messageHandler?.('ws.events', message);

      expect(mockEventsGateway.emitToRoom).not.toHaveBeenCalled();
    });

    it('should ignore chat message with invalid data (non-object)', () => {
      const message = JSON.stringify({
        event: 'chat.inbound_message_received',
        data: 'string-data',
      });

      messageHandler?.('ws.events', message);

      expect(mockEventsGateway.emitToRoom).not.toHaveBeenCalled();
    });
  });

  describe('Redis client edge cases', () => {
    it('should handle error events from subscriber', async () => {
      let errorHandler: ((err: Error) => void) | null = null;
      mockPubSubClient.on.mockImplementation(
        (event: string, handler: (...args: unknown[]) => void) => {
          if (event === 'error') {
            errorHandler = handler as (err: Error) => void;
          }
          if (event === 'message') {
            messageHandler = handler as (
              channel: string,
              message: string,
            ) => void;
          }
        },
      );

      await service.onModuleInit();

      expect(() => {
        errorHandler?.(new Error('Redis connection lost'));
      }).not.toThrow();
    });

    it('should handle connect events from subscriber', async () => {
      let connectHandler: (() => void) | null = null;
      mockPubSubClient.on.mockImplementation(
        (event: string, handler: (...args: unknown[]) => void) => {
          if (event === 'connect') {
            connectHandler = handler as () => void;
          }
          if (event === 'message') {
            messageHandler = handler as (
              channel: string,
              message: string,
            ) => void;
          }
        },
      );

      await service.onModuleInit();

      expect(() => {
        connectHandler?.();
      }).not.toThrow();
    });

    it('should not throw on destroy if subscriber is null', async () => {
      // Don't call onModuleInit, so subscriberClient stays null
      await expect(service.onModuleDestroy()).resolves.not.toThrow();
    });
  });
});
