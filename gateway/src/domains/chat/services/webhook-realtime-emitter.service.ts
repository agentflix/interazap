import { Injectable } from '@nestjs/common';
import { CHAT_EVENTS, tenantRoom, ticketRoom } from '../../../shared/constants';
import { EventsGateway } from '../../realtime/gateways/events.gateway';

/**
 * WebhookRealtimeEmitter
 *
 * Emits webhook events to WebSocket rooms for real-time frontend updates.
 * Routes events to tenant-specific or global rooms based on context.
 */
@Injectable()
export class WebhookRealtimeEmitter {
  constructor(private readonly eventsGateway: EventsGateway) {}

  /**
   * Emits an event to a tenant's room.
   *
   * @param tenantId - Tenant identifier
   * @param channel - Event channel name
   * @param payload - Event payload
   */
  emitToTenant(
    tenantId: string,
    channel: string,
    payload: Record<string, unknown>,
  ): void {
    this.eventsGateway.emitToRoom(tenantRoom(tenantId), channel, payload);
  }

  /**
   * Emits an event to a ticket's room.
   *
   * @param ticketId - Ticket identifier
   * @param channel - Event channel name
   * @param payload - Event payload
   */
  emitToTicket(
    ticketId: string,
    channel: string,
    payload: Record<string, unknown>,
  ): void {
    this.eventsGateway.emitToRoom(ticketRoom(ticketId), channel, payload);
  }

  /**
   * Emits an integration connection event.
   *
   * @param tenantId - Tenant identifier or null for global
   * @param payload - Connection event payload
   */
  emitIntegrationConnection(
    tenantId: string | null,
    payload: Record<string, unknown>,
  ): void {
    if (tenantId) {
      this.emitToTenant(tenantId, CHAT_EVENTS.INTEGRATION_CONNECTION, payload);
      return;
    }

    this.eventsGateway.emit(CHAT_EVENTS.INTEGRATION_CONNECTION, payload);
  }
}
