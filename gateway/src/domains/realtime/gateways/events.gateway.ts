import { Logger, OnModuleInit, OnModuleDestroy } from '@nestjs/common';
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
import { AuthenticatedSocket, JwtPayload } from '../types/socket.types';
import {
  tenantRoom,
  RoomPrefix,
  CHAT_EVENTS,
} from '../../../shared/constants/gateway.constants';
import { WsAuthenticationService } from '../services/ws-authentication.service';
import { WsRoomAccessService } from '../services/ws-room-access.service';
import { WsSessionService } from '../services/ws-session.service';

/**
 * EventsGateway
 *
 * Gateway WebSocket principal do domínio Realtime.
 * Gerencia conexões de clientes socket.io, autentica via JWT/Sanctum,
 * faz ingresso em tenant rooms e distribui eventos para rooms específicas.
 */
@WebSocketGateway({
  path: '/ws',
  pingInterval: 15000,
  pingTimeout: 10000,
  cors: {
    origin: true,
    credentials: true,
  },
})
export class EventsGateway
  implements
    OnModuleInit,
    OnModuleDestroy,
    OnGatewayConnection,
    OnGatewayDisconnect
{
  private readonly logger = new Logger(EventsGateway.name);
  private readonly fileLogger = new GatewayFileLogger(EventsGateway.name);
  private readonly debugChatActivity: boolean;

  @WebSocketServer()
  server!: Server;

  constructor(
    private readonly configService: ConfigService,
    private readonly wsAuthentication: WsAuthenticationService,
    private readonly wsRoomAccess: WsRoomAccessService,
    private readonly wsSession: WsSessionService,
  ) {
    this.debugChatActivity =
      this.configService.get<boolean>('REALTIME_DEBUG_CHAT_ACTIVITY') ===
        true ||
      String(
        this.configService.get<string>('REALTIME_DEBUG_CHAT_ACTIVITY'),
      ).toLowerCase() === 'true';
  }

  /**
   * Inicializa o gateway, registrando o log de estado e
   * configurando o ciclo de vida do serviço de autenticação.
   */
  onModuleInit(): void {
    this.logger.log('EventsGateway initialized');
    this.fileLogger.info('EventsGateway initialized');
    this.wsAuthentication.onModuleInit();
  }

  /**
   * Limpa recursos do serviço de autenticação ao destruir o módulo.
   */
  onModuleDestroy(): void {
    this.wsAuthentication.onModuleDestroy();
  }

  /**
   * Gerencia uma nova conexão WebSocket.
   * Autentica o cliente via token, faz ingresso na tenant room
   * e processa requisições de join pendentes.
   *
   * @param client - Socket.IO Socket recém-conectado
   */
  async handleConnection(client: Socket): Promise<void> {
    const token = this.wsAuthentication.extractToken(client);

    if (!token) {
      this.logger.warn(`Client ${client.id} connected without token`);
      this.fileLogger.error('Client connected without token', {
        clientId: client.id,
      });
      client.disconnect();
      return;
    }

    try {
      const payload = await this.wsAuthentication.verifyToken(token);
      const authClient = client as AuthenticatedSocket;
      authClient.data.user = payload;
      authClient.data.tenantId = payload.tenant_id;

      // Auto-join tenant room
      if (payload.tenant_id) {
        await client.join(tenantRoom(payload.tenant_id));
        this.logger.log(
          `Client ${client.id} authenticated for tenant ${payload.tenant_id}`,
        );
        this.fileLogger.info('Client authenticated and joined tenant room', {
          clientId: client.id,
          tenantId: payload.tenant_id,
          userId: payload.sub,
        });
      }

      await this.wsSession.flushPendingJoinRequests(
        client,
        payload.tenant_id,
        this.server,
      );

      this.logServerStats();
    } catch (error: unknown) {
      this.logger.warn(
        `Client ${client.id} auth failed: ${error instanceof Error ? error.message : String(error)}`,
      );

      this.fileLogger.error('Client authentication failed', {
        clientId: client.id,
        error: error instanceof Error ? error.message : String(error),
      });
      this.wsSession.clearPending(client.id);
      client.disconnect();
    }
  }

  /**
   * Extrai o token de autenticação do handshake do socket.
   *
   * @param client - Socket.IO Socket com handshake HTTP
   * @returns Token Bearer ou `null` se não encontrado
   */
  private extractToken(client: Socket): string | null {
    return this.wsAuthentication.extractToken(client);
  }

  /**
   * Verifica o token via JWT ou Sanctum e retorna o payload autenticado.
   *
   * @param token - Token Bearer a ser verificado
   * @returns Payload JWT com claims `sub` e `tenant_id`
   * @throws Error se o token for inválido
   */
  private async verifyToken(token: string): Promise<JwtPayload> {
    return this.wsAuthentication.verifyToken(token);
  }

  /**
   * Gerencia a desconexão de um cliente WebSocket.
   * Remove requisições pendentes e registra o evento.
   *
   * @param client - Socket.IO Socket desconectado
   */
  handleDisconnect(client: Socket): void {
    this.wsSession.clearPending(client.id);
    this.logger.log(`Client disconnected: ${client.id}`);
    this.fileLogger.info('Client disconnected', { clientId: client.id });
    this.logServerStats();
  }

  /**
   * Registra no log o total de clientes conectados ao servidor WebSocket.
   */
  private logServerStats(): void {
    const sockets = this.server?.sockets?.sockets;
    const count = sockets ? sockets.size : 0;
    this.logger.debug(`Total connected clients: ${count}`);
    this.fileLogger.debug('Total connected clients', { count });
  }

  /**
   * Emite um evento para todos os clientes conectados (broadcast global).
   *
   * @param channel - Nome do canal/evento WebSocket
   * @param payload - Dados a serem enviados
   */
  emit(channel: string, payload: unknown): void {
    this.logger.debug(`Emitting event on ${channel}`);
    this.fileLogger.debug('Emitting broadcast event', {
      channel,
      payloadSize: this.estimatePayloadSize(payload),
    });
    this.server.emit(channel, payload);
  }

  /**
   * Emite evento para uma room específica (tenant_id ou ticket_id).
   */
  emitToRoom(room: string, channel: string, payload: unknown): void {
    const roomSockets = this.server.sockets.adapter.rooms.get(room);
    const clientCount = roomSockets?.size ?? 0;
    if (channel !== CHAT_EVENTS.ACTIVITY || this.debugChatActivity) {
      this.logger.debug(
        `Emitting ${channel} to room ${room} (${clientCount} clients)`,
      );
      this.fileLogger.debug('Emitting room event', {
        channel,
        room,
        clientCount,
        payloadSize: this.estimatePayloadSize(payload),
      });
    }
    this.server.to(room).emit(channel, payload);
  }

  /**
   * Estima o tamanho em bytes do payload serializado como JSON.
   *
   * @param payload - Valor a ser estimado
   * @returns Número de bytes UTF-8 ou `0` em caso de erro de serialização
   */
  private estimatePayloadSize(payload: unknown): number {
    try {
      return Buffer.byteLength(JSON.stringify(payload), 'utf8');
    } catch {
      return 0;
    }
  }

  /**
   * Cliente entra em rooms (tenant, tickets específicos).
   * VALIDAÇÃO DE OWNERSHIP IMPLEMENTADA.
   */
  @SubscribeMessage('join')
  async handleJoin(
    @MessageBody() data: { rooms: string[] },
    @ConnectedSocket() client: Socket,
  ): Promise<void> {
    const rooms = (data?.rooms ?? []).filter(
      (room): room is string => typeof room === 'string' && room.length > 0,
    );
    const authClient = client as AuthenticatedSocket;
    const userTenantId = authClient.data.tenantId ?? '';

    this.logger.log(
      `Client ${client.id} requesting to join rooms: ${rooms.join(', ')}`,
    );
    this.fileLogger.info('Client requested room join', {
      clientId: client.id,
      rooms,
      tenantId: userTenantId,
    });

    if (!userTenantId) {
      this.wsSession.enqueuePendingJoinRequests(client.id, rooms);
      this.logger.debug(
        `Client ${client.id} requested join before auth; queued ${rooms.length} room(s)`,
      );
      this.fileLogger.debug('Client requested room join before auth; queued', {
        clientId: client.id,
        rooms,
      });
      return;
    }

    for (const room of rooms) {
      // Validate room ownership
      if (!(await this.wsRoomAccess.canJoinRoom(room, userTenantId))) {
        this.logger.warn(
          `Client ${client.id} tried to join unauthorized room ${room}`,
        );
        this.fileLogger.error('Unauthorized room join attempt', {
          clientId: client.id,
          room,
          tenantId: userTenantId,
        });
        continue; // Skip this room
      }

      await client.join(room);
      const roomSockets = this.server.sockets.adapter.rooms.get(room);
      const clientCount = roomSockets?.size ?? 0;
      this.logger.log(
        `Client ${client.id} joined room ${room} (now ${clientCount} clients)`,
      );
      this.fileLogger.info('Client joined room', {
        clientId: client.id,
        room,
        clientCount,
      });
    }
  }

  /**
   * Valida se o usuário pode entrar na room solicitada.
   */
  /**
   * Valida se o usuário pode entrar na room solicitada.
   *
   * @param room - Nome da room a ser validada
   * @param userTenantId - ID do tenant do usuário autenticado
   * @returns `true` se o acesso for permitido
   */
  private async canJoinRoom(
    room: string,
    userTenantId: string,
  ): Promise<boolean> {
    return this.wsRoomAccess.canJoinRoom(room, userTenantId);
  }

  /**
   * Valida se o ticket pertence ao tenant do usuário.
   */
  /**
   * Valida se o ticket pertence ao tenant do usuário.
   *
   * @param ticketId - ID do ticket a ser validado
   * @param tenantId - ID do tenant do usuário autenticado
   * @returns `true` se o ticket pertencer ao tenant informado
   */
  private async validateTicketOwnership(
    ticketId: string,
    tenantId: string,
  ): Promise<boolean> {
    return this.wsRoomAccess.canJoinRoom(
      `${RoomPrefix.TICKET}:${ticketId}`,
      tenantId,
    );
  }

  /**
   * Valida se a execução de IA (run) pertence ao tenant do usuário.
   *
   * @param runId - ID da execução de IA a ser validada
   * @param tenantId - ID do tenant do usuário autenticado
   * @returns `true` se o run pertencer ao tenant informado
   */
  private async validateRunOwnership(
    runId: string,
    tenantId: string,
  ): Promise<boolean> {
    return this.wsRoomAccess.canJoinRoom(
      `${RoomPrefix.RUN}:${runId}`,
      tenantId,
    );
  }

  /**
   * Cliente sai de rooms.
   */
  @SubscribeMessage('leave')
  handleLeave(
    @MessageBody() data: { rooms: string[] },
    @ConnectedSocket() client: Socket,
  ): void {
    const rooms = data?.rooms ?? [];
    for (const room of rooms) {
      void client.leave(room);
      this.logger.debug(`Client ${client.id} left room ${room}`);
      this.fileLogger.debug('Client left room', {
        clientId: client.id,
        room,
      });
    }
  }
}
