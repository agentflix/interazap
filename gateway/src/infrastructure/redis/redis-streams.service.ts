/**
 * Redis Streams Service
 *
 * Abstração para operações de Redis Streams (XREAD/XREADGROUP/XADD).
 * Fornece métodos tipados para publish/subscribe com suporte a consumer groups.
 */

import { Injectable, Logger } from '@nestjs/common';
import { RedisService } from './redis.service';
import {
  GatewayMessage,
  createGatewayMessage,
} from '../../common/interfaces/gateway-message.interface';
import {
  GatewayResponse,
  createSuccessResponse,
  createErrorResponse,
  createGatewayError,
} from '../../common/interfaces/gateway-response.interface';

/**
 * Parsed stream message from Redis
 */
export interface StreamMessage<T = unknown> {
  id: string;
  message: GatewayMessage<T>;
}

/**
 * Handler function for stream messages
 */
export type StreamMessageHandler<T = unknown> = (
  message: StreamMessage<T>,
) => Promise<void>;

@Injectable()
export class RedisStreamsService {
  private readonly logger = new Logger(RedisStreamsService.name);

  /**
   * Initializes the streams service with a shared RedisService instance.
   *
   * @param redisService - RedisService for low-level Redis command access
   */
  constructor(private readonly redisService: RedisService) {}

  /**
   * Publish a GatewayMessage to a stream
   *
   * @param stream - Stream name (e.g., 'ai.run.request')
   * @param message - GatewayMessage to publish
   * @returns Stream entry ID
   */
  async publish<T>(
    stream: string,
    message: GatewayMessage<T>,
  ): Promise<string | null> {
    const payload: Record<string, unknown> = {
      correlation_id: message.correlationId,
      timestamp: message.timestamp,
      domain: message.domain,
      action: message.action,
      provider: message.provider,
      payload: JSON.stringify(message.payload),
      metadata: message.metadata ? JSON.stringify(message.metadata) : '',
    };

    this.logger.debug(
      `Publishing to ${stream} [${message.correlationId}]`,
      message.action,
    );

    return this.redisService.publishStream(stream, payload);
  }

  /**
   * Publish a GatewayResponse to a stream
   *
   * @param stream - Stream name (e.g., 'ai.run.response:{correlationId}')
   * @param response - GatewayResponse to publish
   */
  async publishResponse<T>(
    stream: string,
    response: GatewayResponse<T>,
  ): Promise<string | null> {
    const payload: Record<string, unknown> = {
      correlation_id: response.correlationId,
      timestamp: response.timestamp,
      success: response.success,
      data: response.data ? JSON.stringify(response.data) : '',
      error: response.error ? JSON.stringify(response.error) : '',
      processing_time_ms: response.processingTimeMs ?? 0,
    };

    this.logger.debug(
      `Publishing response to ${stream} [${response.correlationId}] success=${response.success}`,
    );

    return this.redisService.publishStream(stream, payload);
  }

  /**
   * Ensure a consumer group exists for a stream
   *
   * @param stream - Stream name
   * @param group - Consumer group name
   */
  async ensureConsumerGroup(stream: string, group: string): Promise<void> {
    try {
      const client = this.redisService.getClient();
      await client.xgroup('CREATE', stream, group, '0', 'MKSTREAM');
      this.logger.log(
        `Consumer group '${group}' created for stream '${stream}'`,
      );
    } catch (error) {
      if ((error as Error).message?.includes('BUSYGROUP')) {
        this.logger.debug(
          `Consumer group '${group}' already exists for '${stream}'`,
        );
        return;
      }
      throw error;
    }
  }

  /**
   * Read messages from a stream using consumer group
   *
   * @param stream - Stream name
   * @param group - Consumer group name
   * @param consumer - Consumer name (unique per instance)
   * @param count - Max messages to read
   * @param blockMs - Block timeout in milliseconds
   * @returns Array of parsed stream messages
   */
  async readGroup<T>(
    stream: string,
    group: string,
    consumer: string,
    count = 10,
    blockMs = 2000,
  ): Promise<StreamMessage<T>[]> {
    const client = this.redisService.getClient();

    try {
      const result = await client.xreadgroup(
        'GROUP',
        group,
        consumer,
        'COUNT',
        count,
        'BLOCK',
        blockMs,
        'STREAMS',
        stream,
        '>',
      );

      if (!result || !Array.isArray(result) || result.length === 0) {
        return [];
      }

      const streamData = result[0];
      if (!Array.isArray(streamData) || streamData.length < 2) {
        return [];
      }

      const messagesData = streamData[1] as Array<[string, string[]]>;
      return messagesData.map(([id, fields]) => ({
        id,
        message: this.parseMessage<T>(fields),
      }));
    } catch (error) {
      this.logger.error(
        `Failed to read from stream '${stream}' group '${group}'`,
        (error as Error).stack,
      );
      return [];
    }
  }

  /**
   * Acknowledge a message as processed
   *
   * @param stream - Stream name
   * @param group - Consumer group name
   * @param messageId - Message ID to acknowledge
   */
  async ack(stream: string, group: string, messageId: string): Promise<void> {
    const client = this.redisService.getClient();
    await client.xack(stream, group, messageId);
    this.logger.debug(`Acknowledged message ${messageId} from ${stream}`);
  }

  /**
   * Parse raw Redis stream fields into GatewayMessage
   */
  private parseMessage<T>(fields: string[]): GatewayMessage<T> {
    const fieldMap: Record<string, string> = {};
    for (let i = 0; i < fields.length; i += 2) {
      fieldMap[fields[i]] = fields[i + 1];
    }

    let payload: T;
    try {
      if (fieldMap.payload) {
        payload = JSON.parse(fieldMap.payload) as T;
      } else {
        const cloned: Record<string, unknown> = { ...fieldMap };
        delete cloned.correlation_id;
        delete cloned.correlationId;
        delete cloned.timestamp;
        delete cloned.domain;
        delete cloned.action;
        delete cloned.provider;
        delete cloned.metadata;
        payload = cloned as T;
      }
    } catch {
      payload = {} as T;
    }

    let metadata: Record<string, unknown> | undefined;
    if (fieldMap.metadata) {
      try {
        metadata = JSON.parse(fieldMap.metadata) as Record<string, unknown>;
      } catch {
        metadata = undefined;
      }
    }

    return createGatewayMessage<T>({
      correlationId:
        fieldMap.correlation_id ??
        fieldMap.correlationId ??
        fieldMap.run_id ??
        `${Date.now()}-${Math.random()}`,
      timestamp: fieldMap.timestamp ?? new Date().toISOString(),
      domain: (fieldMap.domain ?? 'ai') as GatewayMessage['domain'],
      action: fieldMap.action ?? fieldMap.event ?? '',
      provider: fieldMap.provider ?? '',
      payload,
      metadata,
    });
  }

  /**
   * Helper to create success response
   */
  createSuccessResponse<T>(
    correlationId: string,
    data: T,
    processingTimeMs?: number,
  ): GatewayResponse<T> {
    return createSuccessResponse(correlationId, data, processingTimeMs);
  }

  /**
   * Helper to create error response
   */
  createErrorResponse(
    correlationId: string,
    code: Parameters<typeof createGatewayError>[0],
    message: string,
    processingTimeMs?: number,
    details?: unknown,
  ): GatewayResponse<never> {
    return createErrorResponse(
      correlationId,
      createGatewayError(code, message, details),
      processingTimeMs,
    );
  }
}
