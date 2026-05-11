import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import { Logger } from '@nestjs/common';
import { EventsGateway } from './events.gateway';
import { Server, Socket } from 'socket.io';
import { WsAuthenticationService } from '../services/ws-authentication.service';
import { WsRoomAccessService } from '../services/ws-room-access.service';
import { WsSessionService } from '../services/ws-session.service';
import * as jwt from 'jsonwebtoken';

describe('EventsGateway', () => {
  let gateway: EventsGateway;
  let mockServer: Partial<Server>;
  let mockSocket: Partial<Socket>;
  let mockJwt: string;

  const JWT_SECRET = 'test-secret';

  const createMockJwt = (payload: {
    sub: string;
    tenant_id: string;
    email?: string;
  }): string => {
    return jwt.sign(payload, JWT_SECRET, {
      algorithm: 'HS256',
      expiresIn: '1h',
    });
  };

  const mockConfigService = {
    get: jest.fn((key: string): string => {
      if (key === 'jwt.secret') return JWT_SECRET;
      if (key === 'CORS_ORIGINS') return 'http://localhost:4200';
      return '';
    }),
  };

  const mockWsAuthenticationService = {
    onModuleInit: jest.fn(),
    onModuleDestroy: jest.fn(),
    extractToken: jest.fn(),
    verifyToken: jest.fn(),
  };

  const mockWsRoomAccessService = {
    canJoinRoom: jest.fn(),
  };

  const mockWsSessionService = {
    clearPending: jest.fn(),
    enqueuePendingJoinRequests: jest.fn(),
    flushPendingJoinRequests: jest.fn(),
  };

  beforeEach(async () => {
    jest.clearAllMocks();

    // Create valid JWT mock
    mockJwt = createMockJwt({
      sub: 'user-123',
      tenant_id: 'tenant-456',
      email: 'test@test.com',
    });

    // Default mock implementations that delegate to actual socket data
    mockWsAuthenticationService.extractToken.mockImplementation(
      (client: Socket): string | null => {
        const handshake = client.handshake as {
          auth?: { token?: string };
          headers?: { authorization?: string };
        };
        if (handshake.auth?.token) return handshake.auth.token;
        if (handshake.headers?.authorization?.startsWith('Bearer ')) {
          return handshake.headers.authorization.slice(7);
        }
        return null;
      },
    );
    mockWsAuthenticationService.verifyToken.mockImplementation(
      (token: string) => {
        // Check if jwt.secret is configured
        const secret = mockConfigService.get('jwt.secret');
        if (!secret) {
          return Promise.reject(new Error('JWT secret not configured'));
        }
        // No token provided
        if (!token) {
          return Promise.reject(new Error('No token provided'));
        }
        // Invalid token formats
        if (token === 'invalid-token' || token === 'header.payload') {
          return Promise.reject(new Error('Token verification failed'));
        }
        // Check if it's a valid JWT format
        const parts = token.split('.');
        if (parts.length !== 3) {
          return Promise.reject(new Error('Token verification failed'));
        }
        // Try to decode and check payload
        try {
          const payload = JSON.parse(
            Buffer.from(parts[1], 'base64').toString(),
          ) as { sub?: string; tenant_id?: string; exp?: number };
          // Missing required claims
          if (!payload.sub || !payload.tenant_id) {
            return Promise.reject(new Error('Missing required JWT claims'));
          }
          // Check expiration using real jwt.decode to get the actual exp
          const decoded = jwt.decode(token) as { exp?: number } | null;
          if (
            decoded &&
            decoded.exp &&
            decoded.exp < Math.floor(Date.now() / 1000)
          ) {
            return Promise.reject(new Error('Token expired'));
          }
          return Promise.resolve(
            payload as { sub: string; tenant_id: string; email?: string },
          );
        } catch {
          return Promise.reject(new Error('Token verification failed'));
        }
      },
    );
    mockWsRoomAccessService.canJoinRoom.mockImplementation((room: string) => {
      // For malformed room names (empty after prefix)
      if (room === 'tenant:' || room === 'ticket:') {
        return false;
      }
      // For ticket validation - ticket:ticket-123 belongs to tenant-456
      if (room.startsWith('ticket:ticket-123')) {
        return true;
      }
      // For ticket not found
      if (room.startsWith('ticket:nonexistent')) {
        return false;
      }
      // For ticket with error
      if (room.startsWith('ticket:error-ticket')) {
        throw new Error('DB error');
      }
      // For run validation - run:run-123 belongs to tenant-456
      if (room.startsWith('run:run-123')) {
        return true;
      }
      // For tenant validation - only allow matching tenant
      if (room.startsWith('tenant:')) {
        return room === 'tenant:tenant-456';
      }
      return true;
    });
    mockWsSessionService.flushPendingJoinRequests.mockResolvedValue(undefined);
    mockWsSessionService.clearPending.mockReturnValue(undefined);
    mockWsSessionService.enqueuePendingJoinRequests.mockReturnValue(undefined);
    mockWsAuthenticationService.onModuleInit.mockImplementation(() => {
      // Real implementation would start an interval, but we don't need it for most tests
    });
    mockWsAuthenticationService.onModuleDestroy.mockImplementation(() => {
      // Real implementation clears interval
    });

    mockSocket = {
      id: 'socket-123',
      join: jest.fn().mockResolvedValue(undefined),
      leave: jest.fn().mockResolvedValue(undefined),
      disconnect: jest.fn(),
      data: { tenantId: 'tenant-456' }, // Set default tenantId for tests
      handshake: {
        auth: { token: mockJwt },
        query: {},
        headers: {},
      } as any,
    };

    mockServer = {
      emit: jest.fn(),
      to: jest.fn().mockReturnThis(),
      sockets: {
        sockets: new Map([['socket-123', mockSocket as Socket]]),
        adapter: {
          rooms: new Map([['tenant:test-tenant', new Set(['socket-123'])]]),
        },
      } as unknown as Server['sockets'],
    };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        EventsGateway,
        {
          provide: ConfigService,
          useValue: mockConfigService,
        },
        {
          provide: Logger,
          useValue: {
            log: jest.fn(),
            debug: jest.fn(),
            warn: jest.fn(),
            error: jest.fn(),
          },
        },
        {
          provide: WsAuthenticationService,
          useValue: mockWsAuthenticationService,
        },
        {
          provide: WsRoomAccessService,
          useValue: mockWsRoomAccessService,
        },
        {
          provide: WsSessionService,
          useValue: mockWsSessionService,
        },
      ],
    }).compile();

    gateway = module.get<EventsGateway>(EventsGateway);
    gateway.server = mockServer as Server;

    // Mock fileLogger
    (gateway as any).fileLogger = {
      info: jest.fn(),
      error: jest.fn(),
      debug: jest.fn(),
    };
  });

  describe('onModuleInit', () => {
    it('should log initialization', () => {
      expect(() => gateway.onModuleInit()).not.toThrow();
    });
  });

  describe('handleConnection', () => {
    it('should authenticate client with valid token and join tenant room', async () => {
      await gateway.handleConnection(mockSocket as Socket);

      expect(mockSocket.join).toHaveBeenCalledWith('tenant:tenant-456');
      expect(mockSocket.disconnect).not.toHaveBeenCalled();
      // JWT agora inclui exp e iat, então testamos apenas propriedades importantes
      expect((mockSocket as any).data.user).toMatchObject({
        sub: 'user-123',
        tenant_id: 'tenant-456',
        email: 'test@test.com',
      });
      expect((mockSocket as any).data.tenantId).toBe('tenant-456');
    });

    it('should disconnect client when no token is provided', async () => {
      (mockSocket as any).handshake = {
        auth: {},
        query: {},
        headers: {},
      };

      await gateway.handleConnection(mockSocket as Socket);

      expect(mockSocket.disconnect).toHaveBeenCalled();
      expect(mockSocket.join).not.toHaveBeenCalled();
    });

    it('should extract token from authorization header', async () => {
      (mockSocket as any).handshake = {
        auth: {},
        query: {},
        headers: { authorization: `Bearer ${mockJwt}` },
      } as any;

      await gateway.handleConnection(mockSocket as Socket);

      expect(mockSocket.join).toHaveBeenCalledWith('tenant:tenant-456');
      expect(mockSocket.disconnect).not.toHaveBeenCalled();
    });

    it('should disconnect when tenant_id is missing', async () => {
      // JWT sem tenant_id deve falhar na verificação
      const invalidPayloadJwt = jwt.sign(
        {
          sub: 'user-123',
          // tenant_id missing
        },
        JWT_SECRET,
        { algorithm: 'HS256', expiresIn: '1h' },
      );

      (mockSocket as any).handshake = {
        auth: { token: invalidPayloadJwt },
        query: {},
        headers: {},
      } as any;

      await gateway.handleConnection(mockSocket as Socket);

      expect(mockSocket.disconnect).toHaveBeenCalled();
      expect(mockSocket.join).not.toHaveBeenCalled();
    });

    it('should disconnect when token has wrong number of parts', async () => {
      (mockSocket as any).handshake = {
        auth: { token: 'header.payload' }, // Only 2 parts
        query: {},
        headers: {},
      } as any;

      await gateway.handleConnection(mockSocket as Socket);

      expect(mockSocket.disconnect).toHaveBeenCalled();
      expect(mockSocket.join).not.toHaveBeenCalled();
    });

    it('should disconnect when token payload is invalid base64', async () => {
      (mockSocket as any).handshake = {
        auth: { token: 'header.invalid!!!base64.signature' },
        query: {},
        headers: {},
      } as any;

      await gateway.handleConnection(mockSocket as Socket);

      expect(mockSocket.disconnect).toHaveBeenCalled();
      expect(mockSocket.join).not.toHaveBeenCalled();
    });

    it('should disconnect when token payload is not valid JSON', async () => {
      const invalidJson = Buffer.from('not-json{').toString('base64');
      (mockSocket as any).handshake = {
        auth: { token: `header.${invalidJson}.signature` },
        query: {},
        headers: {},
      } as any;

      await gateway.handleConnection(mockSocket as Socket);

      expect(mockSocket.disconnect).toHaveBeenCalled();
      expect(mockSocket.join).not.toHaveBeenCalled();
    });

    it('should disconnect when authorization header has no token', async () => {
      (mockSocket as any).handshake = {
        auth: {},
        query: {},
        headers: { authorization: 'Bearer ' }, // Space but no token
      } as any;

      await gateway.handleConnection(mockSocket as Socket);

      expect(mockSocket.disconnect).toHaveBeenCalled();
      expect(mockSocket.join).not.toHaveBeenCalled();
    });

    it('should disconnect when authorization header is not Bearer type', async () => {
      (mockSocket as any).handshake = {
        auth: {},
        query: {},
        headers: { authorization: 'Basic xyz123' },
      } as any;

      await gateway.handleConnection(mockSocket as Socket);

      expect(mockSocket.disconnect).toHaveBeenCalled();
      expect(mockSocket.join).not.toHaveBeenCalled();
    });
  });

  describe('handleDisconnect', () => {
    it('should log client disconnection', () => {
      expect(() =>
        gateway.handleDisconnect(mockSocket as Socket),
      ).not.toThrow();
    });
  });

  describe('emit', () => {
    it('should emit event to all clients', () => {
      const payload = { message: 'Hello World' };
      gateway.emit('test-channel', payload);

      expect(mockServer.emit).toHaveBeenCalledWith('test-channel', payload);
    });
  });

  describe('emitToRoom', () => {
    it('should emit event to specific room', () => {
      const payload = { data: 'Room message' };
      gateway.emitToRoom('tenant:test-tenant', 'chat.message.new', payload);

      expect(mockServer.to).toHaveBeenCalledWith('tenant:test-tenant');
      expect(mockServer.emit).toHaveBeenCalledWith('chat.message.new', payload);
    });

    it('should emit chat.activity to specific room', () => {
      const payload = { event: 'chat.activity', subevents: [] };
      gateway.emitToRoom('ticket:ticket-123', 'chat.activity', payload);

      expect(mockServer.to).toHaveBeenCalledWith('ticket:ticket-123');
      expect(mockServer.emit).toHaveBeenCalledWith('chat.activity', payload);
    });

    it('should suppress debug logs for chat.activity by default', () => {
      const loggerDebugSpy = jest
        .spyOn((gateway as any).logger, 'debug')
        .mockImplementation(() => undefined);

      const payload = { event: 'chat.activity', subevents: [] };
      gateway.emitToRoom('ticket:ticket-123', 'chat.activity', payload);

      expect(loggerDebugSpy).not.toHaveBeenCalledWith(
        expect.stringContaining('Emitting chat.activity to room'),
      );
      expect((gateway as any).fileLogger.debug).not.toHaveBeenCalledWith(
        'Emitting room event',
        expect.objectContaining({ channel: 'chat.activity' }),
      );
      expect(mockServer.emit).toHaveBeenCalledWith('chat.activity', payload);
    });

    it('should emit debug logs for chat.activity when flag is true', async () => {
      mockConfigService.get.mockImplementation((key: string) => {
        if (key === 'jwt.secret') return JWT_SECRET;
        if (key === 'CORS_ORIGINS') return 'http://localhost:4200';
        if (key === 'REALTIME_DEBUG_CHAT_ACTIVITY') return 'true';
        return '';
      });

      const module: TestingModule = await Test.createTestingModule({
        providers: [
          EventsGateway,
          {
            provide: ConfigService,
            useValue: mockConfigService,
          },
          {
            provide: Logger,
            useValue: {
              log: jest.fn(),
              debug: jest.fn(),
              warn: jest.fn(),
              error: jest.fn(),
            },
          },
          {
            provide: WsAuthenticationService,
            useValue: mockWsAuthenticationService,
          },
          {
            provide: WsRoomAccessService,
            useValue: mockWsRoomAccessService,
          },
          {
            provide: WsSessionService,
            useValue: mockWsSessionService,
          },
        ],
      }).compile();

      const debugGateway = module.get<EventsGateway>(EventsGateway);
      debugGateway.server = mockServer as Server;
      (debugGateway as any).fileLogger = {
        info: jest.fn(),
        error: jest.fn(),
        debug: jest.fn(),
      };

      const loggerDebugSpy = jest
        .spyOn((debugGateway as any).logger, 'debug')
        .mockImplementation(() => undefined);

      const payload = { event: 'chat.activity', subevents: [] };
      debugGateway.emitToRoom('ticket:ticket-123', 'chat.activity', payload);

      expect(loggerDebugSpy).toHaveBeenCalledWith(
        expect.stringContaining('Emitting chat.activity to room'),
      );
      expect((debugGateway as any).fileLogger.debug).toHaveBeenCalledWith(
        'Emitting room event',
        expect.objectContaining({ channel: 'chat.activity' }),
      );
      expect(mockServer.emit).toHaveBeenCalledWith('chat.activity', payload);
    });
  });

  describe('handleJoin', () => {
    it('should allow client to join rooms', async () => {
      const data = { rooms: ['tenant:tenant-456'] };
      await gateway.handleJoin(data, mockSocket as Socket);

      expect(mockSocket.join).toHaveBeenCalledWith('tenant:tenant-456');
    });

    it('should deny joining another tenant room', async () => {
      mockSocket.data.tenantId = 'tenant-456';
      const data = { rooms: ['tenant:tenant-ATTACKER'] };

      await gateway.handleJoin(data, mockSocket as Socket);

      expect(mockSocket.join).not.toHaveBeenCalledWith(
        'tenant:tenant-ATTACKER',
      );
    });

    it('should deny ticket room when ticket belongs to different tenant', async () => {
      mockWsRoomAccessService.canJoinRoom.mockResolvedValueOnce(false);

      const data = { rooms: ['ticket:ticket-123'] };
      await gateway.handleJoin(data, mockSocket as Socket);

      expect(mockSocket.join).not.toHaveBeenCalledWith('ticket:ticket-123');
    });

    it('should reject malformed room names', async () => {
      mockWsRoomAccessService.canJoinRoom.mockResolvedValue(false);

      const data = { rooms: ['tenant:', 'ticket:', 'invalid'] };
      await gateway.handleJoin(data, mockSocket as Socket);

      expect(mockSocket.join).not.toHaveBeenCalled();
    });

    it('should allow run room when run belongs to tenant', async () => {
      mockWsRoomAccessService.canJoinRoom.mockResolvedValueOnce(true);

      const data = { rooms: ['run:run-123'] };
      await gateway.handleJoin(data, mockSocket as Socket);

      expect(mockSocket.join).toHaveBeenCalledWith('run:run-123');
    });

    it('should deny run room when run belongs to another tenant', async () => {
      mockWsRoomAccessService.canJoinRoom.mockResolvedValueOnce(false);

      const data = { rooms: ['run:run-123'] };
      await gateway.handleJoin(data, mockSocket as Socket);

      expect(mockSocket.join).not.toHaveBeenCalledWith('run:run-123');
    });

    it('should handle empty rooms array', async () => {
      const data = { rooms: [] };
      await gateway.handleJoin(data, mockSocket as Socket);

      expect(mockSocket.join).not.toHaveBeenCalled();
    });

    it('should handle undefined rooms', async () => {
      const data = {} as { rooms: string[] };
      await gateway.handleJoin(data, mockSocket as Socket);

      expect(mockSocket.join).not.toHaveBeenCalled();
    });
  });

  describe('handleLeave', () => {
    it('should allow client to leave rooms', () => {
      const data = { rooms: ['tenant:123', 'ticket:456'] };
      gateway.handleLeave(data, mockSocket as Socket);

      expect(mockSocket.leave).toHaveBeenCalledWith('tenant:123');
      expect(mockSocket.leave).toHaveBeenCalledWith('ticket:456');
    });

    it('should handle empty rooms array', () => {
      const data = { rooms: [] };
      gateway.handleLeave(data, mockSocket as Socket);

      expect(mockSocket.leave).not.toHaveBeenCalled();
    });

    it('should handle undefined rooms', () => {
      const data = {} as { rooms: string[] };
      gateway.handleLeave(data, mockSocket as Socket);

      expect(mockSocket.leave).not.toHaveBeenCalled();
    });
  });

  describe('validateTicketOwnership', () => {
    it('should allow ticket belonging to user tenant', async () => {
      mockWsRoomAccessService.canJoinRoom.mockResolvedValueOnce(true);

      const data = { rooms: ['ticket:valid-ticket'] };
      await gateway.handleJoin(data, mockSocket as Socket);

      expect(mockSocket.join).toHaveBeenCalledWith('ticket:valid-ticket');
    });

    it('should deny ticket not found', async () => {
      mockWsRoomAccessService.canJoinRoom.mockResolvedValueOnce(false);

      const data = { rooms: ['ticket:nonexistent'] };
      await gateway.handleJoin(data, mockSocket as Socket);

      expect(mockSocket.join).not.toHaveBeenCalledWith('ticket:nonexistent');
    });

    it('should deny ticket when query fails', async () => {
      // WsRoomAccessService catches db errors and returns false
      mockWsRoomAccessService.canJoinRoom.mockResolvedValueOnce(false);

      const data = { rooms: ['ticket:error-ticket'] };
      await gateway.handleJoin(data, mockSocket as Socket);

      expect(mockSocket.join).not.toHaveBeenCalledWith('ticket:error-ticket');
    });
  });

  describe('verifyToken edge cases', () => {
    it('should reject token when JWT_SECRET is not configured', async () => {
      (mockConfigService.get as jest.Mock).mockImplementation(
        (key: string): string | undefined => {
          if (key === 'jwt.secret') return undefined;
          return '';
        },
      );

      (mockSocket as any).handshake = {
        auth: { token: mockJwt },
        query: {},
        headers: {},
      } as any;

      await gateway.handleConnection(mockSocket as Socket);

      expect(mockSocket.disconnect).toHaveBeenCalled();
    });

    it('should reject expired token', async () => {
      const expiredJwt = jwt.sign(
        { sub: 'user-123', tenant_id: 'tenant-456' },
        JWT_SECRET,
        { algorithm: 'HS256', expiresIn: '-1h' },
      );

      (mockSocket as any).handshake = {
        auth: { token: expiredJwt },
        query: {},
        headers: {},
      } as any;

      await gateway.handleConnection(mockSocket as Socket);

      expect(mockSocket.disconnect).toHaveBeenCalled();
    });

    it('should reject token missing sub claim', async () => {
      const noSubJwt = jwt.sign(
        { tenant_id: 'tenant-456' }, // missing sub
        JWT_SECRET,
        { algorithm: 'HS256', expiresIn: '1h' },
      );

      (mockSocket as any).handshake = {
        auth: { token: noSubJwt },
        query: {},
        headers: {},
      } as any;

      await gateway.handleConnection(mockSocket as Socket);

      expect(mockSocket.disconnect).toHaveBeenCalled();
    });
  });

  describe('logServerStats', () => {
    it('should handle server with undefined sockets', () => {
      gateway.server = { sockets: undefined } as unknown as Server;

      expect(() =>
        gateway.handleDisconnect(mockSocket as Socket),
      ).not.toThrow();
    });
  });

  describe('onModuleDestroy', () => {
    it('should call onModuleDestroy on authentication service', () => {
      gateway.onModuleDestroy();

      expect(mockWsAuthenticationService.onModuleDestroy).toHaveBeenCalled();
    });
  });
});
