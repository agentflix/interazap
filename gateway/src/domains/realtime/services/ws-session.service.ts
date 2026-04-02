import { Logger } from '@nestjs/common';
import { Server, Socket } from 'socket.io';
import { WsRoomAccessService } from './ws-room-access.service';
import { FileLogger } from '../models/ws-session.model';

/**
 * WsSessionService
 *
 * Gerencia o estado de sessão dos clientes WebSocket, incluindo
 * o enfileiramento de requisições de join que chegam antes da autenticação
 * e o processamento dessas requisiões pendentes após autenticação bem-sucedida.
 */
export class WsSessionService {
  private readonly pendingJoinRequests = new Map<string, Set<string>>();
  private static readonly maxPendingRoomsPerClient = 50;

  constructor(
    private readonly logger: Logger,
    private readonly fileLogger: FileLogger,
    private readonly roomAccess: WsRoomAccessService,
  ) {}

  /**
   * Remove todas as requisições de join pendentes para o cliente informado.
   * Chamado quando o cliente desconecta ou a autenticação falha.
   *
   * @param clientId - ID do socket do cliente
   */
  clearPending(clientId: string): void {
    this.pendingJoinRequests.delete(clientId);
  }

  /**
   * Adiciona rooms à fila de join pendente do cliente.
   * Limita a fila ao máximo de `maxPendingRoomsPerClient` entradas para evitar abuso.
   *
   * @param clientId - ID do socket do cliente
   * @param rooms - Lista de nomes de rooms a enfileirar
   */
  enqueuePendingJoinRequests(clientId: string, rooms: string[]): void {
    if (rooms.length === 0) {
      return;
    }

    const pending = this.pendingJoinRequests.get(clientId) ?? new Set<string>();
    for (const room of rooms) {
      if (pending.size >= WsSessionService.maxPendingRoomsPerClient) {
        break;
      }
      pending.add(room);
    }

    this.pendingJoinRequests.set(clientId, pending);
  }

  /**
   * Processa e executa todas as requisições de join pendentes para o cliente.
   * Valida o acesso a cada room via `WsRoomAccessService` antes de efetuar o join.
   *
   * @param client - Socket.IO Socket autenticado
   * @param tenantId - ID do tenant do cliente autenticado
   * @param server - Instância do servidor WebSocket para consulta de rooms
   */
  async flushPendingJoinRequests(
    client: Socket,
    tenantId: string,
    server: Server,
  ): Promise<void> {
    const pending = this.pendingJoinRequests.get(client.id);
    if (!pending || pending.size === 0) {
      return;
    }

    this.pendingJoinRequests.delete(client.id);
    const rooms = Array.from(pending);

    this.logger.debug(
      `Flushing ${rooms.length} queued room join(s) for client ${client.id}`,
    );

    for (const room of rooms) {
      if (!(await this.roomAccess.canJoinRoom(room, tenantId))) {
        this.logger.warn(
          `Client ${client.id} tried to join unauthorized room ${room}`,
        );
        this.fileLogger.error('Unauthorized room join attempt (queued)', {
          clientId: client.id,
          room,
          tenantId,
        });
        continue;
      }

      await client.join(room);
      const roomSockets = server.sockets.adapter.rooms.get(room);
      const clientCount = roomSockets?.size ?? 0;
      this.logger.log(
        `Client ${client.id} joined room ${room} (now ${clientCount} clients)`,
      );
      this.fileLogger.info('Client joined room (queued)', {
        clientId: client.id,
        room,
        clientCount,
      });
    }
  }
}
