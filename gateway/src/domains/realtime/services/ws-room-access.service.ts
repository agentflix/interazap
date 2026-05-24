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
 * Validates WebSocket room join requests against tenant permissions.
 * Checks ownership via api/ HTTP endpoint (replaces direct DB access).
 * Cache L1+L2 with REALTIME strategy (L1 10s, L2 60s).
 */
@Injectable()
export class WsRoomAccessService {
  private readonly logger = new Logger(WsRoomAccessService.name);

  constructor(
    private readonly internalApiClient: InternalApiClientService,
    private readonly cacheService: GatewayCacheService,
  ) {}

  /**
   * Checks whether a user may join a given WebSocket room.
   *
   * Supports three room prefixes:
   * - `tenant:{id}` — direct tenant match (no HTTP call)
   * - `ticket:{id}` — validates via api/ with cache
   * - `run:{id}`    — validates via api/ with cache
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
