import { Injectable, Logger } from '@nestjs/common';
import { BusinessEventContext } from '../models/logger.model';

export type { BusinessEventContext };

/**
 * Logger de eventos de negócio do gateway.
 *
 * Registra eventos significativos do domínio de forma estruturada,
 * sanitizando campos sensíveis antes de emitir os logs.
 */
@Injectable()
export class BusinessEventLogger {
  private readonly logger = new Logger(BusinessEventLogger.name);

  private readonly sensitiveKeys = [
    'password',
    'token',
    'secret',
    'apiKey',
    'api_key',
    'creditCard',
    'credit_card',
  ];

  /**
   * Registra a criação de um ticket.
   *
   * @param ticketId - ID do ticket criado
   * @param context - Contexto adicional do evento
   */
  ticketCreated(ticketId: string, context: BusinessEventContext = {}): void {
    this.log('ticket.created', { ticketId, ...context });
  }

  /**
   * Registra o fechamento de um ticket.
   *
   * @param ticketId - ID do ticket fechado
   * @param status - Status final do ticket
   * @param context - Contexto adicional do evento
   */
  ticketClosed(
    ticketId: string,
    status: string,
    context: BusinessEventContext = {},
  ): void {
    this.log('ticket.closed', { ticketId, status, ...context });
  }

  /**
   * Registra o envio de uma mensagem.
   *
   * @param messageId - ID da mensagem enviada
   * @param ticketId - ID do ticket relacionado
   * @param context - Contexto adicional do evento
   */
  messageSent(
    messageId: string,
    ticketId: string,
    context: BusinessEventContext = {},
  ): void {
    this.log('message.sent', {
      messageId,
      ticketId,
      direction: 'outbound',
      ...context,
    });
  }

  /**
   * Registra o recebimento de uma mensagem.
   *
   * @param messageId - ID da mensagem recebida
   * @param ticketId - ID do ticket relacionado
   * @param channel - Canal de origem da mensagem
   * @param context - Contexto adicional do evento
   */
  messageReceived(
    messageId: string,
    ticketId: string,
    channel: string,
    context: BusinessEventContext = {},
  ): void {
    this.log('message.received', {
      messageId,
      ticketId,
      channel,
      direction: 'inbound',
      ...context,
    });
  }

  /**
   * Registra a conexão de um cliente WebSocket.
   *
   * @param clientId - ID do cliente conectado
   * @param context - Contexto adicional do evento
   */
  wsConnected(clientId: string, context: BusinessEventContext = {}): void {
    this.log('ws.connected', { clientId, ...context });
  }

  /**
   * Registra a desconexão de um cliente WebSocket.
   *
   * @param clientId - ID do cliente desconectado
   * @param reason - Motivo da desconexão
   * @param context - Contexto adicional do evento
   */
  wsDisconnected(
    clientId: string,
    reason: string,
    context: BusinessEventContext = {},
  ): void {
    this.log('ws.disconnected', { clientId, reason, ...context });
  }

  private log(event: string, data: Record<string, unknown>): void {
    const sanitized = this.sanitize(data);
    this.logger.log({
      event,
      timestamp: new Date().toISOString(),
      ...sanitized,
    });
  }

  private sanitize(data: Record<string, unknown>): Record<string, unknown> {
    const result: Record<string, unknown> = {};

    for (const [key, value] of Object.entries(data)) {
      const lowerKey = key.toLowerCase();

      if (this.sensitiveKeys.some((sk) => lowerKey.includes(sk))) {
        result[key] = '[REDACTED]';
        continue;
      }

      if (value && typeof value === 'object' && !Array.isArray(value)) {
        result[key] = this.sanitize(value as Record<string, unknown>);
      } else {
        result[key] = value;
      }
    }

    return result;
  }
}
