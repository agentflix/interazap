import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { DatabaseService } from '../../../infrastructure/database/database.service';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { getString } from '../../../shared/utils/type-guards';
import { StreamPayload } from './chat-webhook.types';
import { WebhookRealtimeEmitter } from './webhook-realtime-emitter.service';

/**
 * ConnectionStatusService
 *
 * Manages WhatsApp instance connection status with event buffering.
 * Normalizes connection states and persists status changes to database.
 */
@Injectable()
export class ConnectionStatusService {
  private readonly logger = new Logger(ConnectionStatusService.name);

  private readonly bufferedConnectionEvents = new Map<
    string,
    {
      tenantId: string | null;
      payload: {
        tenant_id: string | null;
        instance_id: string | null;
        token: string | null;
        status: string;
        connected: boolean;
        qrcode: string | null;
        paircode: string | null;
      };
    }
  >();

  private readonly bufferedConnectionTimers = new Map<
    string,
    ReturnType<typeof setTimeout>
  >();

  private readonly connectionBufferMs: number;

  /**
   * Initializes the service with buffered connection event timing configuration.
   */
  constructor(
    private readonly configService: ConfigService,
    private readonly databaseService: DatabaseService,
    private readonly redisService: RedisService,
    private readonly realtimeEmitter: WebhookRealtimeEmitter,
  ) {
    this.connectionBufferMs = Number(
      this.configService.get<string | number>('CONNECTION_BUFFER_MS') ?? '120',
    );
  }

  /**
   * Processes a connection status event from a webhook stream payload.
   *
   * Terminal states (connected/disconnected) are flushed immediately; intermediate
   * states are buffered and flushed after a configurable delay.
   *
   * @param payload   - StreamPayload carrying tenant and instance metadata.
   * @param instanceId - Optional instance identifier override.
   * @param connection - Raw connection record from the webhook payload.
   * @param statusPayload - Status sub-payload containing the connected flag.
   */
  emitConnectionEvent(
    payload: StreamPayload,
    instanceId: string | undefined,
    connection: Record<string, unknown>,
    statusPayload: Record<string, unknown>,
  ): void {
    const connectionStatus = getString(connection, 'status') ?? 'connecting';
    const isConnected =
      (typeof statusPayload.connected === 'boolean'
        ? statusPayload.connected
        : undefined) ?? connectionStatus === 'connected';

    const isTerminal =
      connectionStatus === 'connected' || connectionStatus === 'disconnected';
    const bufferKey = payload.instance_webhook_token ?? instanceId ?? 'unknown';

    const message = {
      tenant_id: payload.tenant_id ?? null,
      instance_id: instanceId ?? null,
      token:
        getString(connection, 'token') ??
        payload.instance_webhook_token ??
        null,
      status: connectionStatus,
      connected: isConnected,
      qrcode:
        getString(connection, 'qrcode') ??
        getString(connection, 'qr_code') ??
        null,
      paircode:
        getString(connection, 'paircode') ??
        getString(connection, 'pairCode') ??
        null,
    };

    if (isTerminal) {
      this.flushBufferedConnectionEvent(bufferKey);
      this.realtimeEmitter.emitChannelConnection(payload.tenant_id ?? null, {
        ...message,
      });
      return;
    }

    this.bufferedConnectionEvents.set(bufferKey, {
      tenantId: payload.tenant_id ?? null,
      payload: message,
    });
    this.scheduleConnectionFlush(bufferKey);
  }

  /**
   * Persists a normalized connection status to the database and invalidates related Redis caches.
   *
   * @param instanceId - The chat instance primary key.
   * @param status     - Raw status string to be normalized before persistence.
   * @param payload    - StreamPayload with webhook token for cache invalidation.
   * @param connection - Raw connection record used to extract the owner field.
   */
  async updateInstanceConnectionStatus(
    instanceId: string,
    status: string,
    payload: StreamPayload,
    connection: Record<string, unknown>,
  ): Promise<void> {
    const normalizedStatus = this.normalizeInstanceStatus(status);
    if (!normalizedStatus) {
      return;
    }

    try {
      const lastConnection = JSON.stringify({
        status: normalizedStatus,
        connected: normalizedStatus === 'connected',
        updated_at: new Date().toISOString(),
        owner: getString(connection, 'owner') ?? undefined,
      });

      await this.databaseService.query(
        `UPDATE chat_instances
         SET status = $1,
             last_status_at = NOW(),
             settings_json = jsonb_set(
               COALESCE(settings_json::jsonb, '{}'::jsonb),
               '{last_connection}',
               $2::jsonb
             )::json,
             updated_at = NOW()
         WHERE id = $3`,
        [normalizedStatus, lastConnection, instanceId],
      );

      const token = payload.instance_webhook_token ?? '';
      if (token) {
        const cacheKeyBase = `chat.instance_by_webhook_token:${token}`;
        await this.redisService
          .getClient()
          .del(cacheKeyBase, `${cacheKeyBase}:active`, `${cacheKeyBase}:stale`);
      }
    } catch (error) {
      this.logger.error(
        `Failed to update instance status: ${(error as Error)?.message}`,
        (error as Error)?.stack,
      );
    }
  }

  /**
   * Resets the flush timer for a buffered connection event.
   * Cancels any existing pending timer so only the latest event is flushed.
   */
  private scheduleConnectionFlush(bufferKey: string): void {
    const activeTimer = this.bufferedConnectionTimers.get(bufferKey);
    if (activeTimer) {
      clearTimeout(activeTimer);
    }

    const timer = setTimeout(() => {
      this.flushBufferedConnectionEvent(bufferKey);
    }, this.connectionBufferMs);
    this.bufferedConnectionTimers.set(bufferKey, timer);
  }

  /**
   * Emits a buffered connection event and clears its associated timer.
   * No-op if no event is buffered for the given key.
   */
  private flushBufferedConnectionEvent(bufferKey: string): void {
    const activeTimer = this.bufferedConnectionTimers.get(bufferKey);
    if (activeTimer) {
      clearTimeout(activeTimer);
      this.bufferedConnectionTimers.delete(bufferKey);
    }

    const buffered = this.bufferedConnectionEvents.get(bufferKey);
    if (!buffered) {
      return;
    }

    this.bufferedConnectionEvents.delete(bufferKey);
    this.realtimeEmitter.emitChannelConnection(buffered.tenantId, {
      ...buffered.payload,
    });
  }

  /**
   * Normalizes a raw status string to a canonical form: connected | disconnected | qr | connecting | <original>.
   * Returns null for unknown or generic values.
   */
  private normalizeInstanceStatus(status: string): string | null {
    const lower = (status ?? '').toLowerCase().trim();
    if (!lower || lower === 'unknown' || lower === 'generic') {
      return null;
    }

    const connectedAliases = [
      'connected',
      'online',
      'open',
      'ready',
      'authorized',
      'authenticated',
    ];
    if (connectedAliases.includes(lower)) {
      return 'connected';
    }

    const disconnectedAliases = [
      'disconnected',
      'offline',
      'close',
      'closed',
      'logout',
    ];
    if (disconnectedAliases.includes(lower)) {
      return 'disconnected';
    }

    if (lower === 'qr' || lower === 'qrcode') {
      return 'qr';
    }

    if (lower === 'connecting' || lower === 'pairing') {
      return 'connecting';
    }

    return lower;
  }
}
