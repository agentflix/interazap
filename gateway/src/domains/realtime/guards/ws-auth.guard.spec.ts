import { ExecutionContext } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { WsException } from '@nestjs/websockets';
import jwt from 'jsonwebtoken';
import { WsAuthGuard } from './ws-auth.guard';
import { Socket } from 'socket.io';

type MockSocketClient = {
  handshake: {
    auth: {
      token?: string;
    };
    query: Record<string, string>;
    headers: Record<string, string>;
  };
  data: {
    user?: unknown;
    tenantId?: string;
  };
};

describe('WsAuthGuard', () => {
  let guard: WsAuthGuard;
  const jwtSecret = 'test-secret';
  const mockConfigService = {
    get: jest.fn((key: string) => {
      if (key === 'jwt.secret') return jwtSecret;
      if (key === 'jwt.algorithm') return 'HS256';
      return undefined;
    }),
  };

  beforeEach(() => {
    mockConfigService.get.mockClear();
    guard = new WsAuthGuard(mockConfigService as unknown as ConfigService);
  });

  const createMockContext = (client: MockSocketClient): ExecutionContext => {
    return {
      switchToWs: () => ({
        getClient: () => client as Socket,
      }),
    } as ExecutionContext;
  };

  const createValidToken = (payload = {}) =>
    jwt.sign(
      {
        sub: 'user-123',
        tenant_id: 'tenant-456',
        email: 'test@example.com',
        ...payload,
      },
      jwtSecret,
      { algorithm: 'HS256' },
    );

  describe('canActivate', () => {
    it('should throw WsException when no token provided', () => {
      const client = {
        handshake: {
          auth: {},
          query: {},
          headers: {},
        },
        data: {} as MockSocketClient['data'],
      };

      const context = createMockContext(client);

      expect(() => guard.canActivate(context)).toThrow(WsException);
      expect(() => guard.canActivate(context)).toThrow(
        'Missing authentication token',
      );
    });

    it('should authenticate with token from auth object', () => {
      const client = {
        handshake: {
          auth: { token: createValidToken() },
          query: {},
          headers: {},
        },
        data: {} as MockSocketClient['data'],
      };

      const context = createMockContext(client);

      expect(guard.canActivate(context)).toBe(true);
      expect(client.data.user).toBeDefined();
      expect(client.data.tenantId).toBe('tenant-456');
    });

    it('should authenticate with token from query params', () => {
      const client = {
        handshake: {
          auth: {},
          query: { token: createValidToken() },
          headers: {},
        },
        data: {} as MockSocketClient['data'],
      };

      const context = createMockContext(client);

      expect(guard.canActivate(context)).toBe(true);
      expect(client.data.user).toBeDefined();
    });

    it('should authenticate with Bearer token from Authorization header', () => {
      const token = createValidToken();
      const client = {
        handshake: {
          auth: {},
          query: {},
          headers: { authorization: `Bearer ${token}` },
        },
        data: {} as MockSocketClient['data'],
      };

      const context = createMockContext(client);

      expect(guard.canActivate(context)).toBe(true);
    });

    it('should prioritize auth object over query params', () => {
      const client = {
        handshake: {
          auth: { token: createValidToken({ tenant_id: 'tenant-from-auth' }) },
          query: {
            token: createValidToken({ tenant_id: 'tenant-from-query' }),
          },
          headers: {},
        },
        data: {} as MockSocketClient['data'],
      };

      const context = createMockContext(client);

      expect(guard.canActivate(context)).toBe(true);
      expect(client.data.tenantId).toBe('tenant-from-auth');
    });

    it('should throw WsException for invalid token format', () => {
      const client = {
        handshake: {
          auth: { token: 'invalid-token' },
          query: {},
          headers: {},
        },
        data: {} as MockSocketClient['data'],
      };

      const context = createMockContext(client);

      expect(() => guard.canActivate(context)).toThrow(WsException);
      expect(() => guard.canActivate(context)).toThrow(
        'Invalid authentication token',
      );
    });

    it('should throw WsException for token with invalid payload', () => {
      const token = jwt.sign({ foo: 'bar' }, jwtSecret, {
        algorithm: 'HS256',
      });

      const client = {
        handshake: {
          auth: { token },
          query: {},
          headers: {},
        },
        data: {} as MockSocketClient['data'],
      };

      const context = createMockContext(client);

      expect(() => guard.canActivate(context)).toThrow(WsException);
    });

    it('should throw WsException for token missing sub claim', () => {
      const token = jwt.sign({ tenant_id: 't-1' }, jwtSecret, {
        algorithm: 'HS256',
      });

      const client = {
        handshake: {
          auth: { token },
          query: {},
          headers: {},
        },
        data: {} as MockSocketClient['data'],
      };

      const context = createMockContext(client);

      expect(() => guard.canActivate(context)).toThrow(WsException);
    });

    it('should throw WsException for token missing tenant_id claim', () => {
      const token = jwt.sign({ sub: 'user-1' }, jwtSecret, {
        algorithm: 'HS256',
      });

      const client = {
        handshake: {
          auth: { token },
          query: {},
          headers: {},
        },
        data: {} as MockSocketClient['data'],
      };

      const context = createMockContext(client);

      expect(() => guard.canActivate(context)).toThrow(WsException);
    });

    it('should attach user info to socket data', () => {
      const client = {
        handshake: {
          auth: {
            token: createValidToken({
              sub: 'user-abc',
              tenant_id: 'tenant-xyz',
            }),
          },
          query: {},
          headers: {},
        },
        data: {} as MockSocketClient['data'],
      };

      const context = createMockContext(client);

      guard.canActivate(context);

      expect(client.data.user).toEqual(
        expect.objectContaining({
          sub: 'user-abc',
          tenant_id: 'tenant-xyz',
        }),
      );
      expect(client.data.tenantId).toBe('tenant-xyz');
    });
  });
});
