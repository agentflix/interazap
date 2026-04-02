import { Injectable } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';

/** Redis PubSub channel for WebSocket event fan-out. */
@Injectable()
export class GatewayConfig {
  /**
   * Initializes the config service for reading gateway environment variables.
   *
   * @param configService - NestJS config service
   */
  constructor(private readonly configService: ConfigService) {}

  /**
   * Redis PubSub channel used to publish all WebSocket events for fan-out.
   *
   * @remarks
   * Defaults to 'ws.events' if the WS_EVENTS_CHANNEL env var is not set.
   */
  get WS_EVENTS_CHANNEL(): string {
    return this.configService.get<string>('WS_EVENTS_CHANNEL') ?? 'ws.events';
  }

  /**
   * Static prefixes used to namespace WebSocket rooms.
   *
   * @remarks
   * Room names are built as `${prefix}:${id}` (e.g. `tenant:uuid`).
   */
  /** Room prefix enum for WebSocket rooms. */
  readonly RoomPrefix = {
    TENANT: 'tenant',
    TICKET: 'ticket',
    RUN: 'run',
  } as const;

  /** Build tenant room name. */
  tenantRoom(tenantId: string): string {
    return `${this.RoomPrefix.TENANT}:${tenantId}`;
  }

  /** Build ticket room name. */
  ticketRoom(ticketId: string): string {
    return `${this.RoomPrefix.TICKET}:${ticketId}`;
  }

  /** Build AI run room name. */
  runRoom(runId: string): string {
    return `${this.RoomPrefix.RUN}:${runId}`;
  }

  /** Redis Stream names used across the gateway. */
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

  /** AI domain event names. */
  readonly AI_EVENTS = {
    RUN_STREAMING: 'ai.run.streaming',
    RUN_COMPLETED: 'ai.run.completed',
  } as const;

  /** Chat domain event names. */
  readonly CHAT_EVENTS = {
    ACTIVITY: 'chat.activity',
    MESSAGE_NEW: 'chat.message.new',
    MESSAGE_STATUS: 'chat.message.status',
    MESSAGE_EDIT: 'chat.message.edit',
    CONNECTION: 'chat.connection',
    CONTACT: 'chat.contact',
    INTEGRATION_CONNECTION: 'integration.connection',
    TYPING: 'chat.typing',
  } as const;
}

// Backward-compatible static exports for non-injected contexts
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
  INTEGRATION_CONNECTION: 'integration.connection',
  TYPING: 'chat.typing',
} as const;
