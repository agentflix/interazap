import { Injectable } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';

/**
 * Configurações centralizadas do gateway: canais Redis PubSub, prefixos de sala WebSocket
 * e nomes de eventos de domínio (AI, Chat).
 *
 * Contexto: injetado em serviços que precisam de nomes de canais/streams/eventos
 * configuráveis via variáveis de ambiente.
 */
@Injectable()
export class GatewayConfig {
  /**
   * Inicializa o serviço de configuração para leitura de variáveis de ambiente do gateway.
   *
   * @param configService - Serviço de configuração do NestJS
   */
  constructor(private readonly configService: ConfigService) {}

  /**
   * Canal Redis PubSub usado para publicar todos os eventos WebSocket para fan-out entre instâncias.
   * Padrão: 'ws.events' quando WS_EVENTS_CHANNEL não estiver configurado.
   */
  get WS_EVENTS_CHANNEL(): string {
    return this.configService.get<string>('WS_EVENTS_CHANNEL') ?? 'ws.events';
  }

  /**
   * Prefixos para nomear salas WebSocket no formato `${prefixo}:${id}` (ex.: `tenant:uuid`).
   */
  readonly RoomPrefix = {
    TENANT: 'tenant',
    TICKET: 'ticket',
    RUN: 'run',
  } as const;

  /** Constrói o nome da sala WebSocket para um tenant. */
  tenantRoom(tenantId: string): string {
    return `${this.RoomPrefix.TENANT}:${tenantId}`;
  }

  /** Constrói o nome da sala WebSocket para um ticket. */
  ticketRoom(ticketId: string): string {
    return `${this.RoomPrefix.TICKET}:${ticketId}`;
  }

  /** Constrói o nome da sala WebSocket para uma execução de IA. */
  runRoom(runId: string): string {
    return `${this.RoomPrefix.RUN}:${runId}`;
  }

  /** Nomes dos Redis Streams utilizados em todo o gateway. */
  get STREAM_NAMES() {
    return {
      CHAT_INBOUND_MESSAGE:
        this.configService.get<string>('CHAT_INBOUND_STREAM') ??
        'chat.inbound_message_received',
      CHAT_OUTBOUND_MESSAGE:
        this.configService.get<string>('CHAT_OUTBOUND_STREAM') ??
        'chat.outbound_message',
      CHAT_OUTBOUND_STATUS:
        this.configService.get<string>('CHAT_OUTBOUND_STATUS_STREAM') ??
        'chat.outbound_message_status',
      BILLING_PAYMENT:
        this.configService.get<string>('BILLING_STREAM_NAME') ??
        'billing.payment_received',
      AI_TOOL_REQUEST:
        this.configService.get<string>('AI_TOOL_REQUEST_STREAM') ??
        'ai.tool.request',
    } as const;
  }

  /** Nomes de eventos do domínio de IA emitidos via WebSocket. */
  readonly AI_EVENTS = {
    RUN_STREAMING: 'ai.run.streaming',
    RUN_COMPLETED: 'ai.run.completed',
  } as const;

  /** Nomes de eventos do domínio de chat emitidos via WebSocket. */
  readonly CHAT_EVENTS = {
    ACTIVITY: 'chat.activity',
    MESSAGE_NEW: 'chat.message.new',
    MESSAGE_STATUS: 'chat.message.status',
    MESSAGE_EDIT: 'chat.message.edit',
    CONNECTION: 'chat.connection',
    CONTACT: 'chat.contact',
    CHANNEL_CONNECTION: 'channel.connection',
    TYPING: 'chat.typing',
  } as const;
}

// Exportações estáticas compatíveis para contextos sem injeção de dependência
export const RoomPrefix = {
  TENANT: 'tenant',
  TICKET: 'ticket',
  RUN: 'run',
} as const;
export const tenantRoom = (tenantId: string): string =>
  `${RoomPrefix.TENANT}:${tenantId}`;
export const ticketRoom = (ticketId: string): string =>
  `${RoomPrefix.TICKET}:${ticketId}`;
export const runRoom = (runId: string): string => `${RoomPrefix.RUN}:${runId}`;
export const AI_EVENTS = {
  RUN_STREAMING: 'ai.run.streaming',
  RUN_COMPLETED: 'ai.run.completed',
} as const;
export const CHAT_EVENTS = {
  ACTIVITY: 'chat.activity',
  MESSAGE_NEW: 'chat.message.new',
  MESSAGE_STATUS: 'chat.message.status',
  MESSAGE_EDIT: 'chat.message.edit',
  CONNECTION: 'chat.connection',
  CONTACT: 'chat.contact',
  CHANNEL_CONNECTION: 'channel.connection',
  TYPING: 'chat.typing',
} as const;
