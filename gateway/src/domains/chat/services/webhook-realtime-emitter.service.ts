import { Injectable } from '@nestjs/common';
import { CHAT_EVENTS, tenantRoom, ticketRoom } from '../../../shared/constants';
import { EventsGateway } from '../../realtime/gateways/events.gateway';

/**
 * Emite eventos de webhook para salas WebSocket para atualizacoes em tempo real no frontend.
 *
 * Contexto: roteia eventos para salas especificas de tenant ou globais com base no contexto
 * do evento processado pelo ChatWebhookRealtimeProcessor.
 */
@Injectable()
export class WebhookRealtimeEmitter {
  constructor(private readonly eventsGateway: EventsGateway) {}

  /**
   * Emite um evento para a sala de um tenant.
   *
   * @param tenantId - Identificador do tenant
   * @param channel - Nome do canal de evento
   * @param payload - Payload do evento
   */
  emitToTenant(
    tenantId: string,
    channel: string,
    payload: Record<string, unknown>,
  ): void {
    this.eventsGateway.emitToRoom(tenantRoom(tenantId), channel, payload);
  }

  /**
   * Emite um evento para a sala de um ticket.
   *
   * @param ticketId - Identificador do ticket
   * @param channel - Nome do canal de evento
   * @param payload - Payload do evento
   */
  emitToTicket(
    ticketId: string,
    channel: string,
    payload: Record<string, unknown>,
  ): void {
    this.eventsGateway.emitToRoom(ticketRoom(ticketId), channel, payload);
  }

  /**
   * Emite um evento de conexao de canal WhatsApp.
   *
   * @param tenantId - Identificador do tenant ou null para emissao global
   * @param payload - Payload do evento de conexao
   */
  emitChannelConnection(
    tenantId: string | null,
    payload: Record<string, unknown>,
  ): void {
    if (tenantId) {
      this.emitToTenant(tenantId, CHAT_EVENTS.CHANNEL_CONNECTION, payload);
      return;
    }

    this.eventsGateway.emit(CHAT_EVENTS.CHANNEL_CONNECTION, payload);
  }
}
