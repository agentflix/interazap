import { Injectable, Logger } from '@nestjs/common';
import { InternalApiClientService } from '../../../infrastructure/internal-api/internal-api-client.service';
import { GatewayCacheService } from '../../../infrastructure/cache/gateway-cache.service';
import { CacheStrategies } from '../../../infrastructure/cache/gateway-cache.types';
import { RoomPrefix } from '../../../shared/constants/gateway.constants';

interface RoomAccessResponse {
  allowed: boolean;
}

/**
 * WsRoomAccessService
 *
 * Valida as requisições de ingresso em rooms WebSocket contra as permissões do tenant.
 * Verifica ownership via endpoint HTTP da api/ (substitui acesso direto ao banco).
 * Cache L1+L2 com estratégia REALTIME (L1 10s, L2 60s).
 */
@Injectable()
export class WsRoomAccessService {
  private readonly logger = new Logger(WsRoomAccessService.name);

  constructor(
    private readonly internalApiClient: InternalApiClientService,
    private readonly cacheService: GatewayCacheService,
  ) {}

  /**
   * Verifica se o usuário pode ingressar na room WebSocket solicitada.
   *
   * Suporta três prefixos de room:
   * - `tenant:{id}` — verificação direta por tenant_id (sem chamada HTTP)
   * - `ticket:{id}` — valida via api/ com cache
   * - `run:{id}`    — valida via api/ com cache
   *
   * @param room - Nome da room a ser verificada
   * @param userTenantId - ID do tenant do usuário autenticado
   * @returns `true` se o acesso for permitido
   */
  async canJoinRoom(room: string, userTenantId: string): Promise<boolean> {
    if (room.startsWith(`${RoomPrefix.TENANT}:`)) {
      const roomTenantId = room.split(':')[1];
      if (!roomTenantId) {
        this.logger.warn(`Invalid tenant room format: ${room}`);
        return false;
      }
      return roomTenantId === userTenantId;
    }

    if (
      room.startsWith(`${RoomPrefix.TICKET}:`) ||
      room.startsWith(`${RoomPrefix.RUN}:`)
    ) {
      return this.checkRoomAccess(room, userTenantId);
    }

    this.logger.warn(`Unknown room format: ${room}`);
    return false;
  }

  /**
   * Consulta a api/ para verificar se o tenant tem acesso à room solicitada.
   * Resultado cacheado com estratégia REALTIME.
   *
   * @param room - Nome da room (ticket:{id} ou run:{id})
   * @param tenantId - ID do tenant a ser validado
   * @returns `true` se o acesso for permitido pela api/
   */
  private async checkRoomAccess(
    room: string,
    tenantId: string,
  ): Promise<boolean> {
    const cacheKey = `realtime:room-access:${room}:${tenantId}`;

    try {
      const result = await this.cacheService.getOrFetch<RoomAccessResponse>(
        cacheKey,
        () =>
          this.internalApiClient.get<RoomAccessResponse>(
            `/api/internal/realtime/room-access?room=${encodeURIComponent(room)}&tenant_id=${encodeURIComponent(tenantId)}`,
            'realtime_room_access',
          ),
        CacheStrategies.REALTIME,
        'realtime_room_access',
      );

      return result.allowed === true;
    } catch (error) {
      const message = error instanceof Error ? error.message : String(error);
      this.logger.error(`Failed to validate room access: ${message}`);
      return false;
    }
  }
}
