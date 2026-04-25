import {
  Body,
  Controller,
  Logger,
  Post,
  UseGuards,
  UsePipes,
  ValidationPipe,
} from '@nestjs/common';
import { InternalApiKeyGuard } from '../realtime/guards/internal-api-key.guard';
import { EventsGateway } from '../realtime/gateways/events.gateway';
import { WebChatGateway } from '../realtime/gateways/webchat.gateway';
import { tenantRoom, ticketRoom, CHAT_EVENTS } from '../../shared/constants';
import type {
  BroadcastEventDto,
  MessageStatusEventDto,
  NewMessageEventDto,
} from './models/internal-broadcast.model';

/**
 * InternalController
 *
 * Controller interno para comunicação do Laravel via WebSocket.
 * Expõe endpoints protegidos por API key interna para broadcast
 * de eventos aos clientes Angular conectados.
 * Não deve ser exposto externamente.
 */
@Controller({ version: '1', path: 'internal' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class InternalController {
  private readonly logger = new Logger(InternalController.name);

  constructor(
    private readonly eventsGateway: EventsGateway,
    private readonly webChatGateway: WebChatGateway,
  ) {}

  /**
   * Broadcast genérico de evento para todos os clientes ou para uma room específica.
   *
   * Rooms de sessão webchat (session:*) são roteadas para o WebChatGateway,
   * que opera no namespace /webchat onde os visitantes estão conectados.
   * Demais rooms usam o EventsGateway (namespace dos agentes).
   */
  @Post('broadcast/event')
  broadcastEvent(@Body() payload: BroadcastEventDto): { success: boolean } {
    this.logger.debug(
      `Broadcasting event: ${payload.event} to room: ${payload.room ?? 'all'}`,
    );

    if (payload.room) {
      if (payload.room.startsWith('session:')) {
        this.webChatGateway.emitToRoom(
          payload.room,
          payload.event,
          payload.data,
        );
      } else {
        this.eventsGateway.emitToRoom(
          payload.room,
          payload.event,
          payload.data,
        );
      }
    } else {
      this.eventsGateway.emit(payload.event, payload.data);
    }

    return { success: true };
  }

  /**
   * Broadcast de status de mensagem de chat.
   * Emite para a room do tenant e para a room do ticket.
   */
  @Post('broadcast/message-status')
  broadcastMessageStatus(@Body() payload: MessageStatusEventDto): {
    success: boolean;
  } {
    this.logger.debug(
      `Broadcasting message status: ${payload.message_id} -> ${payload.status}`,
    );

    const eventData = {
      message_id: payload.message_id,
      ticket_id: payload.ticket_id,
      status: payload.status,
      error_message: payload.error_message,
      sent_at: payload.sent_at,
      delivered_at: payload.delivered_at,
      read_at: payload.read_at,
    };

    // Emite para a room do tenant
    if (payload.tenant_id) {
      this.eventsGateway.emitToRoom(
        tenantRoom(payload.tenant_id),
        CHAT_EVENTS.MESSAGE_STATUS,
        eventData,
      );
    }

    // Emite para a room do ticket (para quem está visualizando o ticket)
    if (payload.ticket_id) {
      this.eventsGateway.emitToRoom(
        ticketRoom(payload.ticket_id),
        CHAT_EVENTS.MESSAGE_STATUS,
        eventData,
      );
    }

    return { success: true };
  }

  /**
   * Broadcast de nova mensagem de chat.
   * Emite para a room do tenant e para a room do ticket.
   */
  @Post('broadcast/new-message')
  broadcastNewMessage(@Body() payload: NewMessageEventDto): {
    success: boolean;
  } {
    this.logger.debug(
      `Broadcasting new message: ${payload.message.id} to ticket: ${payload.ticket_id}`,
    );

    const eventData = {
      ticket_id: payload.ticket_id,
      message: payload.message,
    };

    // Emite para a room do tenant
    if (payload.tenant_id) {
      this.eventsGateway.emitToRoom(
        tenantRoom(payload.tenant_id),
        CHAT_EVENTS.MESSAGE_NEW,
        eventData,
      );
    }

    // Emite para a room do ticket
    if (payload.ticket_id) {
      this.eventsGateway.emitToRoom(
        ticketRoom(payload.ticket_id),
        CHAT_EVENTS.MESSAGE_NEW,
        eventData,
      );
    }

    return { success: true };
  }
}
