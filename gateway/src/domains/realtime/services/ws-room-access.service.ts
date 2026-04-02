import { Logger } from '@nestjs/common';
import { DatabaseService } from '../../../infrastructure/database/database.service';
import { RoomPrefix } from '../../../shared/constants/gateway.constants';

/**
 * WsRoomAccessService
 *
 * Validates WebSocket room join requests against tenant permissions.
 * Checks ownership for tenant, ticket, and AI run rooms.
 */
export class WsRoomAccessService {
  constructor(
    private readonly databaseService: DatabaseService,
    private readonly logger: Logger,
  ) {}

  /**
   * Checks whether a user may join a given WebSocket room.
   *
   * Supports three room prefixes:
   * - `tenant:{id}` — direct tenant match
   * - `ticket:{id}` — validates ticket ownership via database
   * - `run:{id}`    — validates AI run ownership via database
   *
   * @param room        - Full room name (e.g. `ticket:uuid`)
   * @param userTenantId - Tenant ID of the requesting user
   * @returns `true` when access is allowed, `false` otherwise
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

    if (room.startsWith(`${RoomPrefix.TICKET}:`)) {
      const ticketId = room.split(':')[1];
      if (!ticketId) {
        this.logger.warn(`Invalid ticket room format: ${room}`);
        return false;
      }
      return await this.validateTicketOwnership(ticketId, userTenantId);
    }

    if (room.startsWith(`${RoomPrefix.RUN}:`)) {
      const runId = room.split(':')[1];
      if (!runId) {
        this.logger.warn(`Invalid run room format: ${room}`);
        return false;
      }
      return await this.validateRunOwnership(runId, userTenantId);
    }

    this.logger.warn(`Unknown room format: ${room}`);
    return false;
  }

  /**
   * Validates that a chat ticket belongs to the given tenant.
   * Queries `chat_tickets.tenant_id` directly from the database.
   */
  private async validateTicketOwnership(
    ticketId: string,
    tenantId: string,
  ): Promise<boolean> {
    try {
      const result = await this.databaseService.query<{ tenant_id: string }>(
        'SELECT tenant_id FROM chat_tickets WHERE id = $1 LIMIT 1',
        [ticketId],
      );

      if (result.rows.length === 0) {
        this.logger.warn(`Ticket ${ticketId} not found`);
        return false;
      }

      return result.rows[0].tenant_id === tenantId;
    } catch (error) {
      const message = error instanceof Error ? error.message : String(error);
      this.logger?.error(`Failed to validate ticket ownership: ${message}`);
      return false;
    }
  }

  /**
   * Validates that an AI autopilot run belongs to the given tenant.
   * Queries `ai_autopilot_runs.tenant_id` directly from the database.
   */
  private async validateRunOwnership(
    runId: string,
    tenantId: string,
  ): Promise<boolean> {
    try {
      const result = await this.databaseService.query<{ tenant_id: string }>(
        'SELECT tenant_id FROM ai_autopilot_runs WHERE id = $1 LIMIT 1',
        [runId],
      );

      if (result.rows.length === 0) {
        this.logger.warn(`Run ${runId} not found`);
        return false;
      }

      return result.rows[0].tenant_id === tenantId;
    } catch (error) {
      const message = error instanceof Error ? error.message : String(error);
      this.logger?.error(`Failed to validate run ownership: ${message}`);
      return false;
    }
  }
}
