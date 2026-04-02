import { Injectable } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';

/** Redis PubSub channel for WebSocket event fan-out. */
@Injectable()
export class GatewayConfigService {
  /**
   * Initializes the config service with NestJS ConfigService for environment variable access.
   *
   * @param configService - NestJS ConfigService instance (injected)
   */
  constructor(private readonly configService: ConfigService) {}

  /**
   * Checks if the application is running in a test environment.
   * Uses NODE_ENV from ConfigService and JEST_WORKER_ID which is set by Jest runtime.
   */
  isTestEnvironment(): boolean {
    const nodeEnv = this.configService.get<string>('NODE_ENV');
    return nodeEnv === 'test' || process.env['JEST_WORKER_ID'] !== undefined;
  }

  /**
   * Redis PubSub channel used to fan-out WebSocket events to all gateway instances.
   *
   * @returns Channel name, defaults to `ws.events`
   */
  get wsEventsChannel(): string {
    return this.configService.get<string>('WS_EVENTS_CHANNEL') ?? 'ws.events';
  }

  /**
   * Redis Streams stream name for inbound chat messages received by the gateway.
   *
   * @returns Stream name, defaults to `chat.inbound_message_received`
   */
  get chatInboundStream(): string {
    return (
      this.configService.get<string>('CHAT_INBOUND_STREAM') ??
      'chat.inbound_message_received'
    );
  }

  /**
   * Redis Streams stream name for outbound chat messages sent by the gateway.
   *
   * @returns Stream name, defaults to `chat.outbound_message`
   */
  get chatOutboundStream(): string {
    return (
      this.configService.get<string>('CHAT_OUTBOUND_STREAM') ??
      'chat.outbound_message'
    );
  }

  /**
   * Redis Streams stream name for outbound chat message status updates (delivered, read, failed).
   *
   * @returns Stream name, defaults to `chat.outbound_message_status`
   */
  get chatOutboundStatusStream(): string {
    return (
      this.configService.get<string>('CHAT_OUTBOUND_STATUS_STREAM') ??
      'chat.outbound_message_status'
    );
  }

  /**
   * Redis Streams stream name for billing payment events received by the gateway.
   *
   * @returns Stream name, defaults to `billing.payment_received`
   */
  get billingStreamName(): string {
    return (
      this.configService.get<string>('BILLING_STREAM_NAME') ??
      'billing.payment_received'
    );
  }

  /**
   * Redis Streams stream name for AI tool invocation requests dispatched by the gateway.
   *
   * @returns Stream name, defaults to `ai.tool.request`
   */
  get aiToolRequestStream(): string {
    return (
      this.configService.get<string>('AI_TOOL_REQUEST_STREAM') ??
      'ai.tool.request'
    );
  }
}
