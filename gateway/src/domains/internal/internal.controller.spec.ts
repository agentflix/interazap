import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import { InternalController } from './internal.controller';
import { EventsGateway } from '../realtime/gateways/events.gateway';
import { WebChatGateway } from '../realtime/gateways/webchat.gateway';
import { InternalApiKeyGuard } from '../realtime/guards/internal-api-key.guard';

describe('InternalController', () => {
  let controller: InternalController;
  let eventsGateway: jest.Mocked<EventsGateway>;
  let webChatGateway: jest.Mocked<WebChatGateway>;

  beforeEach(async () => {
    const mockEventsGateway = {
      emit: jest.fn(),
      emitToRoom: jest.fn(),
    };

    const mockWebChatGateway = {
      emitToRoom: jest.fn(),
    };

    const module: TestingModule = await Test.createTestingModule({
      controllers: [InternalController],
      providers: [
        {
          provide: EventsGateway,
          useValue: mockEventsGateway,
        },
        {
          provide: WebChatGateway,
          useValue: mockWebChatGateway,
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

    controller = module.get<InternalController>(InternalController);
    eventsGateway = module.get(EventsGateway);
    webChatGateway = module.get(WebChatGateway);
  });

  describe('broadcastEvent', () => {
    it('should broadcast to specific room via EventsGateway', () => {
      const payload = {
        event: 'custom.event',
        room: 'room-1',
        data: { message: 'test' },
      };

      const result = controller.broadcastEvent(payload);

      expect(result).toEqual({ success: true });
      expect(eventsGateway.emitToRoom).toHaveBeenCalledWith(
        'room-1',
        'custom.event',
        { message: 'test' },
      );
    });

    it('should route session: rooms to WebChatGateway', () => {
      const payload = {
        event: 'webchat:agent_message',
        room: 'session:abc-123',
        data: { message: 'hello from agent' },
      };

      const result = controller.broadcastEvent(payload);

      expect(result).toEqual({ success: true });
      expect(webChatGateway.emitToRoom).toHaveBeenCalledWith(
        'session:abc-123',
        'webchat:agent_message',
        { message: 'hello from agent' },
      );
      expect(eventsGateway.emitToRoom).not.toHaveBeenCalled();
    });

    it('should broadcast globally when no room specified', () => {
      const payload = {
        event: 'global.event',
        data: { message: 'global' },
      };

      controller.broadcastEvent(payload);

      expect(eventsGateway.emit).toHaveBeenCalledWith('global.event', {
        message: 'global',
      });
    });
  });

  describe('broadcastMessageStatus', () => {
    it('should broadcast message status to tenant and ticket rooms', () => {
      const payload = {
        message_id: 'msg-1',
        ticket_id: 'ticket-1',
        tenant_id: 'tenant-1',
        status: 'delivered' as const,
      };

      const result = controller.broadcastMessageStatus(payload);

      expect(result).toEqual({ success: true });
      expect(eventsGateway.emitToRoom).toHaveBeenCalledWith(
        'tenant:tenant-1',
        'chat.message.status',
        expect.objectContaining({ message_id: 'msg-1' }),
      );
      expect(eventsGateway.emitToRoom).toHaveBeenCalledWith(
        'ticket:ticket-1',
        'chat.message.status',
        expect.any(Object),
      );
    });

    it('should handle optional fields', () => {
      const payload = {
        message_id: 'msg-2',
        ticket_id: 'ticket-2',
        tenant_id: 'tenant-2',
        status: 'failed' as const,
        error_message: 'Network error',
        sent_at: '2024-01-01T00:00:00Z',
      };

      controller.broadcastMessageStatus(payload);

      expect(eventsGateway.emitToRoom).toHaveBeenCalled();
    });
  });

  describe('broadcastNewMessage', () => {
    it('should broadcast new message to tenant and ticket rooms', () => {
      const payload = {
        ticket_id: 'ticket-1',
        tenant_id: 'tenant-1',
        message: {
          id: 'msg-1',
          content: 'Hello',
          type: 'text',
        },
      };

      const result = controller.broadcastNewMessage(payload);

      expect(result).toEqual({ success: true });
      expect(eventsGateway.emitToRoom).toHaveBeenCalledTimes(2);
    });

    it('should handle messages with minimal fields', () => {
      const payload = {
        ticket_id: 'ticket-1',
        tenant_id: 'tenant-1',
        message: {
          id: 'msg-1',
        },
      };

      controller.broadcastNewMessage(payload);

      expect(eventsGateway.emitToRoom).toHaveBeenCalledTimes(2);
    });
  });
});
