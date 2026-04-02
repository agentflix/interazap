import {
  Body,
  Controller,
  Logger,
  Post,
  UseGuards,
  UsePipes,
  ValidationPipe,
} from '@nestjs/common';
import { EventsGateway } from '../gateways/events.gateway';
import { InternalApiKeyGuard } from '../guards/internal-api-key.guard';
import {
  tenantRoom,
  ticketRoom,
  runRoom,
  CHAT_EVENTS,
} from '../../../shared/constants';
import type {
  BroadcastEventDto,
  MessageStatusDto,
  NewMessageDto,
  AiRunEventDto,
} from '../models/internal-broadcast.model';

/**
 * InternalBroadcastController
 *
 * Controller interno para receber broadcasts do Laravel e distribuí-los
 * via WebSocket para os clientes conectados.
 * Endpoints acessíveis apenas internamente (protegidos por `InternalApiKeyGuard`).
 */
@Controller({ version: '1', path: 'internal/broadcast' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class InternalBroadcastController {
  private readonly logger = new Logger(InternalBroadcastController.name);

  constructor(private readonly eventsGateway: EventsGateway) {}

  /**
   * Realiza o broadcast de um evento genérico para uma room específica, tenant room ou globalmente.
   * Se `room` for fornecida, emite para ela; se `data.tenant_id` estiver presente, emite para a tenant room;
   * caso contrário, faz broadcast global.
   *
   * @param dto - Payload do evento com nome, room opcional e dados
   * @returns Objeto indicando sucesso ou falha da operação
   */
  @Post('event')
  broadcastEvent(@Body() dto: BroadcastEventDto): { success: boolean } {
    try {
      const { event, room, data } = dto;

      this.logger.debug(`Broadcasting event: ${event}`, {
        room,
        dataKeys: Object.keys(data),
      });

      if (room) {
        this.eventsGateway.emitToRoom(room, event, data);
      } else if (data.tenant_id !== undefined) {
        this.eventsGateway.emitToRoom(
          tenantRoom(String(data.tenant_id as string | number)),
          event,
          data,
        );
      } else {
        // Broadcast global (todos os clientes)
        this.eventsGateway.emit(event, data);
      }

      return { success: true };
    } catch (error) {
      this.logger.error(
        `Failed to broadcast event: ${(error as Error).message}`,
        { event: dto.event },
      );
      return { success: false };
    }
  }

  /**
   * Distribui uma atualização de status de mensagem para a tenant room e para a ticket room.
   *
   * @param dto - Payload com IDs de mensagem, ticket, tenant e novo status
   * @returns Objeto indicando sucesso ou falha da operação
   */
  @Post('message-status')
  broadcastMessageStatus(@Body() dto: MessageStatusDto): { success: boolean } {
    try {
      const { tenant_id, ticket_id } = dto;

      this.logger.debug('Broadcasting message status', {
        messageId: dto.message_id,
        status: dto.status,
        tenantId: tenant_id,
        ticketId: ticket_id,
      });

      // Emitir para tenant room
      if (tenant_id) {
        this.eventsGateway.emitToRoom(
          tenantRoom(tenant_id),
          CHAT_EVENTS.MESSAGE_STATUS,
          dto,
        );
      }

      // Emitir para ticket room (se existir)
      if (ticket_id) {
        this.eventsGateway.emitToRoom(
          ticketRoom(ticket_id),
          CHAT_EVENTS.MESSAGE_STATUS,
          dto,
        );
      }

      return { success: true };
    } catch (error) {
      this.logger.error(
        `Failed to broadcast message status: ${(error as Error).message}`,
        { messageId: dto.message_id },
      );
      return { success: false };
    }
  }

  /**
   * Distribui uma nova mensagem de chat para a tenant room e para a ticket room.
   *
   * @param dto - Payload com ID do ticket, tenant e dados da mensagem
   * @returns Objeto indicando sucesso ou falha da operação
   */
  @Post('new-message')
  broadcastNewMessage(@Body() dto: NewMessageDto): { success: boolean } {
    try {
      const { tenant_id, ticket_id } = dto;

      this.logger.debug('Broadcasting new message', {
        tenantId: tenant_id,
        ticketId: ticket_id,
      });

      // Emitir para tenant room
      if (tenant_id) {
        this.eventsGateway.emitToRoom(
          tenantRoom(tenant_id),
          CHAT_EVENTS.MESSAGE_NEW,
          dto,
        );
      }

      // Emitir para ticket room (se existir)
      if (ticket_id) {
        this.eventsGateway.emitToRoom(
          ticketRoom(ticket_id),
          CHAT_EVENTS.MESSAGE_NEW,
          dto,
        );
      }

      return { success: true };
    } catch (error) {
      this.logger.error(
        `Failed to broadcast new message: ${(error as Error).message}`,
        { ticketId: dto.ticket_id },
      );
      return { success: false };
    }
  }

  /**
   * Distribui um evento de execução de IA para a tenant room e para a run room.
   *
   * @param dto - Payload com nome do evento e dados contendo `tenant_id` e `run_id`
   * @returns Objeto indicando sucesso ou falha da operação
   */
  @Post('ai-run-event')
  broadcastAiRunEvent(@Body() dto: AiRunEventDto): { success: boolean } {
    try {
      const { event, data } = dto;
      const tenantId = data.tenant_id;

      this.logger.debug('Broadcasting AI run event', {
        event,
        tenantId,
        runId: data.run_id,
      });

      if (typeof tenantId === 'string' && tenantId !== '') {
        this.eventsGateway.emitToRoom(tenantRoom(tenantId), event, data);
      }

      const runId = data.run_id;
      if (typeof runId === 'string' && runId !== '') {
        this.eventsGateway.emitToRoom(runRoom(runId), event, data);
      }

      return { success: true };
    } catch (error) {
      this.logger.error(
        `Failed to broadcast AI run event: ${(error as Error).message}`,
        { event: dto.event },
      );
      return { success: false };
    }
  }
}
