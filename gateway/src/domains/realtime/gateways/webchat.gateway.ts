import { Logger, OnModuleInit } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import {
  WebSocketGateway,
  WebSocketServer,
  SubscribeMessage,
  MessageBody,
  ConnectedSocket,
  OnGatewayConnection,
  OnGatewayDisconnect,
} from '@nestjs/websockets';
import { Server, Socket } from 'socket.io';
import { GatewayFileLogger } from '../../../common/logger/gateway-file-logger';
import { WsAuthenticationService } from '../services/ws-authentication.service';
import { tenantRoom } from '../../../shared/constants/gateway.constants';

/**
 * WebChatGateway
 *
 * Gateway WebSocket dedicado ao webchat público.
 * Gerencia conexões de visitantes via token de sessão (não JWT do agente),
 * permite ingresso em rooms de sessão e distribui eventos de IA em tempo real.
 *
 * O namespace '/webchat' combinado com path '/ws' resulta em '/ws/webchat',
 * que é o endpoint esperado pelo WebChatService do Angular.
 */
@WebSocketGateway({
  namespace: '/webchat',
  path: '/ws',
  pingInterval: 15000,
  pingTimeout: 10000,
  cors: {
    origin: true,
    credentials: true,
  },
})
export class WebChatGateway
  implements OnModuleInit, OnGatewayConnection, OnGatewayDisconnect
{
  private readonly logger = new Logger(WebChatGateway.name);
  private readonly fileLogger = new GatewayFileLogger(WebChatGateway.name);

  @WebSocketServer()
  server!: Server;

  constructor(
    private readonly configService: ConfigService,
    private readonly wsAuthentication: WsAuthenticationService,
  ) {}

  onModuleInit(): void {
    this.logger.log('WebChatGateway initialized');
    this.fileLogger.info('WebChatGateway initialized');
  }

  /**
   * Gerencia nova conexão WebSocket do webchat público.
   * Valida token de sessão e extrai tenant_id e session_id.
   */
  async handleConnection(client: Socket): Promise<void> {
    const token = this.wsAuthentication.extractToken(client);

    if (!token) {
      this.logger.warn(`WebChat client ${client.id} connected without token`);
      client.disconnect();
      return;
    }

    try {
      // Validate session token (different from agent JWT)
      // WebChatService uses session token stored in sessionToken
      const payload = await this.wsAuthentication.verifyToken(token);

      if (!payload) {
        this.logger.warn(`WebChat client ${client.id} invalid session token`);
        client.disconnect();
        return;
      }

      // Store session data on socket
      (
        client as Socket & { data: { sessionId?: string; tenantId?: string } }
      ).data = {
        sessionId: payload.session_id ?? payload.sub,
        tenantId: payload.tenant_id,
      };

      this.logger.log(
        `WebChat client ${client.id} connected for session ${payload.session_id ?? payload.sub}`,
      );
      this.fileLogger.info('WebChat client connected', {
        clientId: client.id,
        sessionId: payload.session_id ?? payload.sub,
        tenantId: payload.tenant_id,
      });
    } catch (error: unknown) {
      this.logger.warn(
        `WebChat client ${client.id} auth failed: ${error instanceof Error ? error.message : String(error)}`,
      );
      client.disconnect();
    }
  }

  /**
   * Gerencia desconexão do cliente webchat.
   */
  handleDisconnect(client: Socket): void {
    const clientData = (
      client as Socket & { data: { sessionId?: string; tenantId?: string } }
    ).data;
    this.logger.log(`WebChat client disconnected: ${client.id}`);
    this.fileLogger.info('WebChat client disconnected', {
      clientId: client.id,
    });
  }

  /**
   * Cliente webchat entra na room da sessão para receber eventos.
   * Também entra na tenant room para eventos globais.
   *
   * Event: webchat:join
   * Payload: { sessionId: string }
   */
  @SubscribeMessage('webchat:join')
  async handleWebChatJoin(
    @MessageBody() data: { sessionId: string },
    @ConnectedSocket() client: Socket,
  ): Promise<void> {
    const clientData = (
      client as Socket & { data: { sessionId?: string; tenantId?: string } }
    ).data;
    const sessionId = data?.sessionId ?? clientData.sessionId;
    const tenantId = clientData.tenantId;

    if (!sessionId) {
      this.logger.warn(
        `WebChat client ${client.id} sent webchat:join without sessionId`,
      );
      client.emit('webchat:error', {
        code: 'INVALID_SESSION',
        message: 'sessionId is required',
      });
      return;
    }

    const sessionRoom = `session:${sessionId}`;
    await client.join(sessionRoom);

    this.logger.log(
      `WebChat client ${client.id} joined session room ${sessionRoom}`,
    );
    this.fileLogger.info('WebChat client joined session room', {
      clientId: client.id,
      sessionId,
      sessionRoom,
    });

    // Also join tenant room if tenant_id is available
    if (tenantId) {
      const tenant = tenantRoom(tenantId);
      await client.join(tenant);
      this.logger.log(
        `WebChat client ${client.id} also joined tenant room ${tenant}`,
      );
    }

    // Confirm join to client
    client.emit('webchat:joined', { sessionId });
  }

  /**
   * Emite evento para uma room específica no namespace webchat.
   */
  emitToRoom(room: string, channel: string, payload: unknown): void {
    this.logger.debug(`Emitting ${channel} to room ${room}`);
    this.fileLogger.debug('Emitting room event in webchat namespace', {
      channel,
      room,
    });
    this.server.to(room).emit(channel, payload);
  }

  /**
   * Cliente webchat sai da room da sessão.
   *
   * Event: webchat:leave
   * Payload: { sessionId: string }
   */
  @SubscribeMessage('webchat:leave')
  handleWebChatLeave(
    @MessageBody() data: { sessionId: string },
    @ConnectedSocket() client: Socket,
  ): void {
    const sessionId = data?.sessionId;
    if (!sessionId) return;

    const sessionRoom = `session:${sessionId}`;
    void client.leave(sessionRoom);
    this.logger.debug(
      `WebChat client ${client.id} left session room ${sessionRoom}`,
    );
  }
}
