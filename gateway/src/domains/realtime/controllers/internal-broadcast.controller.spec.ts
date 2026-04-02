import { Test, TestingModule } from '@nestjs/testing';
import { InternalBroadcastController } from './internal-broadcast.controller';
import { EventsGateway } from '../gateways/events.gateway';
import { Logger } from '@nestjs/common';
import { InternalApiKeyGuard } from '../guards/internal-api-key.guard';

describe('InternalBroadcastController', () => {
  let controller: InternalBroadcastController;
  let eventsGateway: jest.Mocked<EventsGateway>;

  beforeEach(async () => {
    const mockEventsGateway = {
      emit: jest.fn(),
      emitToRoom: jest.fn(),
    };

    const module: TestingModule = await Test.createTestingModule({
      controllers: [InternalBroadcastController],
      providers: [
        {
          provide: EventsGateway,
          useValue: mockEventsGateway,
        },
      ],
    })
      .overrideGuard(InternalApiKeyGuard)
      .useValue({ canActivate: () => true })
      .compile();

    controller = module.get<InternalBroadcastController>(
      InternalBroadcastController,
    );
    eventsGateway = module.get(EventsGateway);

    jest.spyOn(Logger.prototype, 'debug').mockImplementation();
    jest.spyOn(Logger.prototype, 'error').mockImplementation();
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  describe('broadcastEvent', () => {
    it('should broadcast to specific room', () => {
      const dto = {
        event: 'test.event',
        room: 'room-123',
        data: { message: 'Hello' },
      };

      const result = controller.broadcastEvent(dto);

      expect(result).toEqual({ success: true });
      expect(eventsGateway.emitToRoom).toHaveBeenCalledWith(
        'room-123',
        'test.event',
        { message: 'Hello' },
      );
      expect(eventsGateway.emit).not.toHaveBeenCalled();
    });

    it('should broadcast to tenant room when tenant_id in data', () => {
      const dto = {
        event: 'test.event',
        room: null,
        data: { tenant_id: 'tenant-456', message: 'Hello' },
      };

      const result = controller.broadcastEvent(dto);

      expect(result).toEqual({ success: true });
      expect(eventsGateway.emitToRoom).toHaveBeenCalledWith(
        'tenant:tenant-456',
        'test.event',
        { tenant_id: 'tenant-456', message: 'Hello' },
      );
    });

    it('should broadcast globally when no room and no tenant_id', () => {
      const dto = {
        event: 'test.event',
        data: { message: 'Global broadcast' },
      };

      const result = controller.broadcastEvent(dto);

      expect(result).toEqual({ success: true });
      expect(eventsGateway.emit).toHaveBeenCalledWith('test.event', {
        message: 'Global broadcast',
      });
      expect(eventsGateway.emitToRoom).not.toHaveBeenCalled();
    });

    it('should prioritize room over tenant_id', () => {
      const dto = {
        event: 'test.event',
        room: 'specific-room',
        data: { tenant_id: 'tenant-456', message: 'Hello' },
      };

      controller.broadcastEvent(dto);

      expect(eventsGateway.emitToRoom).toHaveBeenCalledWith(
        'specific-room',
        'test.event',
        expect.any(Object),
      );
      expect(eventsGateway.emitToRoom).not.toHaveBeenCalledWith(
        'tenant:tenant-456',
        expect.any(String),
        expect.any(Object),
      );
    });

    it('should handle errors and return success false', () => {
      eventsGateway.emit.mockImplementation(() => {
        throw new Error('Broadcast failed');
      });

      const dto = {
        event: 'test.event',
        data: { message: 'Test' },
      };

      const result = controller.broadcastEvent(dto);

      expect(result).toEqual({ success: false });
    });

    it('should log debug info on successful broadcast', () => {
      const loggerSpy = jest.spyOn(Logger.prototype, 'debug');
      const dto = {
        event: 'test.event',
        room: 'room-123',
        data: { key1: 'value1', key2: 'value2' },
      };

      controller.broadcastEvent(dto);

      expect(loggerSpy).toHaveBeenCalled();
    });

    it('should log error on failure', () => {
      const loggerSpy = jest.spyOn(Logger.prototype, 'error');
      eventsGateway.emitToRoom.mockImplementation(() => {
        throw new Error('Gateway error');
      });

      const dto = {
        event: 'test.event',
        room: 'room-123',
        data: {},
      };

      controller.broadcastEvent(dto);

      expect(loggerSpy).toHaveBeenCalled();
    });
  });

  describe('broadcastMessageStatus', () => {
    it('should broadcast to tenant and ticket rooms', () => {
      const dto = {
        message_id: 'msg-123',
        ticket_id: 'ticket-456',
        tenant_id: 'tenant-789',
        status: 'delivered',
      };

      const result = controller.broadcastMessageStatus(dto);

      expect(result).toEqual({ success: true });
      expect(eventsGateway.emitToRoom).toHaveBeenCalledWith(
        'tenant:tenant-789',
        'chat.message.status',
        dto,
      );
      expect(eventsGateway.emitToRoom).toHaveBeenCalledWith(
        'ticket:ticket-456',
        'chat.message.status',
        dto,
      );
      expect(eventsGateway.emitToRoom).toHaveBeenCalledTimes(2);
    });

    it('should broadcast only to tenant room when no ticket_id', () => {
      const dto = {
        message_id: 'msg-123',
        ticket_id: '',
        tenant_id: 'tenant-789',
        status: 'sent',
      };

      controller.broadcastMessageStatus(dto);

      expect(eventsGateway.emitToRoom).toHaveBeenCalledWith(
        'tenant:tenant-789',
        'chat.message.status',
        dto,
      );
      expect(eventsGateway.emitToRoom).toHaveBeenCalledTimes(1);
    });

    it('should include optional fields in broadcast', () => {
      const dto = {
        message_id: 'msg-123',
        ticket_id: 'ticket-456',
        tenant_id: 'tenant-789',
        status: 'failed',
        error_message: 'Network error',
        sent_at: '2024-01-01T00:00:00Z',
        delivered_at: null,
        read_at: null,
      };

      controller.broadcastMessageStatus(dto);

      expect(eventsGateway.emitToRoom).toHaveBeenCalledWith(
        'tenant:tenant-789',
        'chat.message.status',
        dto,
      );
    });

    it('should handle errors and return success false', () => {
      eventsGateway.emitToRoom.mockImplementation(() => {
        throw new Error('Broadcast error');
      });

      const dto = {
        message_id: 'msg-123',
        ticket_id: 'ticket-456',
        tenant_id: 'tenant-789',
        status: 'sent',
      };

      const result = controller.broadcastMessageStatus(dto);

      expect(result).toEqual({ success: false });
    });
  });

  describe('broadcastNewMessage', () => {
    it('should broadcast to tenant and ticket rooms', () => {
      const dto = {
        ticket_id: 'ticket-123',
        tenant_id: 'tenant-456',
        message: {
          id: 'msg-789',
          content: 'Hello World',
          from: 'user-1',
        },
      };

      const result = controller.broadcastNewMessage(dto);

      expect(result).toEqual({ success: true });
      expect(eventsGateway.emitToRoom).toHaveBeenCalledWith(
        'tenant:tenant-456',
        'chat.message.new',
        dto,
      );
      expect(eventsGateway.emitToRoom).toHaveBeenCalledWith(
        'ticket:ticket-123',
        'chat.message.new',
        dto,
      );
      expect(eventsGateway.emitToRoom).toHaveBeenCalledTimes(2);
    });

    it('should broadcast only to tenant room when no ticket_id', () => {
      const dto = {
        ticket_id: '',
        tenant_id: 'tenant-456',
        message: { id: 'msg-789' },
      };

      controller.broadcastNewMessage(dto);

      expect(eventsGateway.emitToRoom).toHaveBeenCalledWith(
        'tenant:tenant-456',
        'chat.message.new',
        dto,
      );
      expect(eventsGateway.emitToRoom).toHaveBeenCalledTimes(1);
    });

    it('should handle complex message objects', () => {
      const dto = {
        ticket_id: 'ticket-123',
        tenant_id: 'tenant-456',
        message: {
          id: 'msg-789',
          type: 'text',
          content: 'Test message',
          metadata: {
            source: 'whatsapp',
            timestamp: Date.now(),
          },
          attachments: [],
        },
      };

      controller.broadcastNewMessage(dto);

      expect(eventsGateway.emitToRoom).toHaveBeenCalledWith(
        'tenant:tenant-456',
        'chat.message.new',
        dto,
      );
    });

    it('should handle errors and return success false', () => {
      eventsGateway.emitToRoom.mockImplementation(() => {
        throw new Error('Gateway error');
      });

      const dto = {
        ticket_id: 'ticket-123',
        tenant_id: 'tenant-456',
        message: {},
      };

      const result = controller.broadcastNewMessage(dto);

      expect(result).toEqual({ success: false });
    });

    it('should log error on failure', () => {
      const loggerSpy = jest.spyOn(Logger.prototype, 'error');
      eventsGateway.emitToRoom.mockImplementation(() => {
        throw new Error('Gateway error');
      });

      const dto = {
        ticket_id: 'ticket-123',
        tenant_id: 'tenant-456',
        message: {},
      };

      controller.broadcastNewMessage(dto);

      expect(loggerSpy).toHaveBeenCalled();
    });
  });

  describe('broadcastAiRunEvent', () => {
    it('should broadcast ai run event to tenant and run rooms', () => {
      const dto = {
        event: 'ai.run.completed',
        data: {
          tenant_id: 'tenant-123',
          run_id: 'run-456',
          status: 'completed',
        },
      };

      const result = controller.broadcastAiRunEvent(dto);

      expect(result).toEqual({ success: true });
      expect(eventsGateway.emitToRoom).toHaveBeenCalledWith(
        'tenant:tenant-123',
        'ai.run.completed',
        dto.data,
      );
      expect(eventsGateway.emitToRoom).toHaveBeenCalledWith(
        'run:run-456',
        'ai.run.completed',
        dto.data,
      );
    });

    it('should return success false on gateway error', () => {
      eventsGateway.emitToRoom.mockImplementation(() => {
        throw new Error('Gateway error');
      });

      const result = controller.broadcastAiRunEvent({
        event: 'ai.run.failed',
        data: {
          tenant_id: 'tenant-123',
          run_id: 'run-456',
        },
      });

      expect(result).toEqual({ success: false });
    });
  });
});
