import { ExecutionContext } from '@nestjs/common';
import { Reflector } from '@nestjs/core';
import { ThrottlerException, ThrottlerStorageService } from '@nestjs/throttler';
import { Socket } from 'socket.io';
import { WsThrottlerGuard } from './ws-throttler.guard';

describe('WsThrottlerGuard', () => {
  let guard: WsThrottlerGuard;

  const handler = () => undefined;
  class TestGateway {}

  const createContext = (
    options: {
      socketId?: string;
      userSub?: string;
      forwardedFor?: string;
    } = {},
  ): ExecutionContext => {
    const headers: Record<string, string> = {
      'user-agent': 'jest',
      'x-forwarded-for': options.forwardedFor ?? '127.0.0.1',
    };

    const client: Partial<Socket> = {
      id: options.socketId ?? 'socket-1',
      handshake: {
        headers,
        address: headers['x-forwarded-for'],
      } as any,
      data: {
        user: {
          sub: options.userSub ?? 'user-123',
          tenant_id: 'tenant-456',
        },
      } as any,
    };

    const response = {
      header: jest.fn(),
    };

    return {
      getType: () => 'ws',
      getHandler: () => handler,
      getClass: () => TestGateway,
      getArgs: () => [],
      getArgByIndex: () => undefined,
      switchToWs: () => ({
        getClient: () => client as Socket,
        getData: () => ({}),
      }),
      switchToHttp: () => ({
        getRequest: () => ({ headers, ip: '127.0.0.1' }),
        getResponse: () => response,
        getNext: () => undefined,
      }),
      switchToRpc: () => ({
        getContext: () => undefined,
        getData: () => undefined,
      }),
    } as unknown as ExecutionContext;
  };

  beforeEach(async () => {
    guard = new WsThrottlerGuard(
      [
        {
          limit: 2,
          ttl: 60,
        },
      ],
      new ThrottlerStorageService(),
      new Reflector(),
    );

    await guard.onModuleInit();
  });

  it('should block websocket messages after exceeding the limit', async () => {
    const context = createContext();

    await expect(guard.canActivate(context)).resolves.toBe(true);
    await expect(guard.canActivate(context)).resolves.toBe(true);
    await expect(guard.canActivate(context)).rejects.toBeInstanceOf(
      ThrottlerException,
    );
  });

  it('should share quota across sockets from the same user', async () => {
    const first = createContext({
      forwardedFor: '10.0.0.1',
      socketId: 'socket-a',
    });
    const second = createContext({
      forwardedFor: '10.0.0.2',
      socketId: 'socket-b',
    });
    const third = createContext({
      forwardedFor: '10.0.0.3',
      socketId: 'socket-c',
    });

    await expect(guard.canActivate(first)).resolves.toBe(true);
    await expect(guard.canActivate(second)).resolves.toBe(true);
    await expect(guard.canActivate(third)).rejects.toBeInstanceOf(
      ThrottlerException,
    );
  });
});
