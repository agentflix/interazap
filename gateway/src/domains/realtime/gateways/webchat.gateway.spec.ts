import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import { Socket } from 'socket.io';
import { WebChatGateway } from './webchat.gateway';
import { WsAuthenticationService } from '../services/ws-authentication.service';
import { GatewayFileLogger } from '../../../common/logger/gateway-file-logger';

type ClientData = { sessionId?: string; tenantId?: string };

function makeClient(data: ClientData): Socket {
  return {
    id: 'mock-socket-id',
    data,
    join: jest.fn().mockResolvedValue(undefined),
    leave: jest.fn().mockResolvedValue(undefined),
    emit: jest.fn(),
    disconnect: jest.fn(),
  } as unknown as Socket;
}

describe('WebChatGateway — security: sessionId always from JWT token', () => {
  let gateway: WebChatGateway;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: [
        WebChatGateway,
        {
          provide: ConfigService,
          useValue: { get: jest.fn() },
        },
        {
          provide: WsAuthenticationService,
          useValue: {
            extractToken: jest.fn(),
            verifyWebChatToken: jest.fn(),
          },
        },
        {
          provide: GatewayFileLogger,
          useValue: {
            info: jest.fn(),
            debug: jest.fn(),
            warn: jest.fn(),
            error: jest.fn(),
          },
        },
      ],
    }).compile();

    gateway = module.get<WebChatGateway>(WebChatGateway);

    jest.spyOn((gateway as any).logger, 'log').mockReturnValue(undefined);
    jest.spyOn((gateway as any).logger, 'warn').mockReturnValue(undefined);
    jest.spyOn((gateway as any).logger, 'debug').mockReturnValue(undefined);
    jest.spyOn((gateway as any).fileLogger, 'info').mockReturnValue(undefined);
    jest.spyOn((gateway as any).fileLogger, 'debug').mockReturnValue(undefined);
  });

  describe('handleWebChatJoin', () => {
    it('ignora data.sessionId e entra na room da própria sessão do token', async () => {
      const client = makeClient({
        sessionId: 'session-A',
        tenantId: 'tenant-1',
      });

      await gateway.handleWebChatJoin(
        { sessionId: 'session-B' } as any,
        client,
      );

      expect(client.join).toHaveBeenCalledWith('session:session-A');
      expect(client.join).not.toHaveBeenCalledWith('session:session-B');
      expect(client.disconnect).not.toHaveBeenCalled();
    });

    it('entra na room correta quando payload não tem sessionId', async () => {
      const client = makeClient({
        sessionId: 'session-A',
        tenantId: 'tenant-1',
      });

      await gateway.handleWebChatJoin({} as any, client);

      expect(client.join).toHaveBeenCalledWith('session:session-A');
      expect(client.disconnect).not.toHaveBeenCalled();
    });

    it('desconecta e emite INVALID_SESSION quando token não tem sessionId', async () => {
      const client = makeClient({ tenantId: 'tenant-1' });

      await gateway.handleWebChatJoin({} as any, client);

      expect(client.disconnect).toHaveBeenCalled();
      expect(client.join).not.toHaveBeenCalled();
      expect(client.emit).toHaveBeenCalledWith('webchat:error', {
        code: 'INVALID_SESSION',
        message: 'Session not found in token',
      });
    });
  });

  describe('handleWebChatLeave', () => {
    it('ignora data.sessionId e sai da room da própria sessão do token', () => {
      const client = makeClient({
        sessionId: 'session-A',
        tenantId: 'tenant-1',
      });

      gateway.handleWebChatLeave({ sessionId: 'session-B' } as any, client);

      expect(client.leave).toHaveBeenCalledWith('session:session-A');
      expect(client.leave).not.toHaveBeenCalledWith('session:session-B');
    });

    it('não faz nada quando token não tem sessionId', () => {
      const client = makeClient({ tenantId: 'tenant-1' });

      gateway.handleWebChatLeave({} as any, client);

      expect(client.leave).not.toHaveBeenCalled();
    });
  });
});
