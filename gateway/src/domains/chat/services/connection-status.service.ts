import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { Queue } from 'bullmq';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { BullMQQueueFactory } from '../../../shared/services/queue/bullmq-queue-factory.service';
import { getString } from '../../../shared/utils/type-guards';
import { StreamPayload } from './chat-webhook.types';
import { WebhookRealtimeEmitter } from './webhook-realtime-emitter.service';

/**
 * Servico de gerenciamento de status de conexao de instancias WhatsApp com buffering de eventos.
 *
 * Contexto: normaliza estados de conexao recebidos de provedores (Uazapi, Z-API, Meta),
 * emite eventos realtime via WebSocket, persiste mudancas no banco de dados atraves
 * da api/ HTTP (via BullMQ job), e invalida caches Redis de resolucao de instancias.
 * Estados intermediarios (connecting, qr) sao bufferizados por CONNECTION_BUFFER_MS
 * antes de ser emitidos, enquanto estados terminais (connected/disconnected) sao
 * emitidos imediatamente.
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

  static readonly CONNECTION_STATUS_QUEUE = 'update-connection-status';

  private readonly connectionBufferMs: number;
  private connectionStatusQueue: Queue | null = null;

  constructor(
    private readonly configService: ConfigService,
    private readonly redisService: RedisService,
    private readonly realtimeEmitter: WebhookRealtimeEmitter,
    private readonly queueFactory: BullMQQueueFactory,
  ) {
    this.connectionBufferMs = Number(
      this.configService.get<string | number>('CONNECTION_BUFFER_MS') ?? '120',
    );
  }

  /**
   * Processa um evento de status de conexao a partir do payload de stream do webhook.
   *
   * Estados terminais (connected/disconnected) sao emitidos imediatamente;
   * estados intermediarios sao bufferizados e emitidos apos o atraso configuravel.
   *
   * @param payload Payload de stream com metadados de tenant e instancia
   * @param instanceId Identificador de instancia opcional como sobrescrita
   * @param connection Record de conexao bruto do payload do webhook
   * @param statusPayload Sub-payload de status contendo o flag connected
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
   * Persiste um status de conexao normalizado no banco de dados e invalida caches Redis relacionados.
   *
   * @param instanceId Chave primaria da instancia de chat
   * @param status String de status bruta a ser normalizada antes da persistencia
   * @param payload Payload de stream com token do webhook para invalidacao de cache
   * @param connection Record de conexao bruto usado para extrair o campo owner
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

    const queue = this.ensureConnectionStatusQueue();
    void queue
      .add('update-connection-status', {
        instanceId,
        status: normalizedStatus,
        connectedAt: new Date().toISOString(),
        owner: getString(connection, 'owner') ?? undefined,
        webhookToken: payload.instance_webhook_token ?? undefined,
      })
      .catch((err: unknown) =>
        this.logger.error(
          `Failed to enqueue UpdateConnectionStatusJob: ${err instanceof Error ? err.message : String(err)}`,
        ),
      );

    const token = payload.instance_webhook_token ?? '';
    if (token) {
      const cacheKeyBase = `chat.instance_by_webhook_token:${token}`;
      await this.redisService
        .getClient()
        .del(cacheKeyBase, `${cacheKeyBase}:active`, `${cacheKeyBase}:stale`);
    }
  }

  /**
   * Retorna a fila BullMQ de status de conexao, criando-a se necessario.
   */
  private ensureConnectionStatusQueue(): Queue {
    if (!this.connectionStatusQueue) {
      this.connectionStatusQueue = this.queueFactory.createQueue(
        ConnectionStatusService.CONNECTION_STATUS_QUEUE,
      );
    }
    return this.connectionStatusQueue;
  }

  /**
   * Reagenda o timer de flush para um evento de conexao bufferizado.
   * Cancela qualquer timer pendente existente para que apenas o evento mais recente seja emitido.
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
   * Emite um evento de conexao bufferizado e limpa o timer associado.
   * Nao faz nada quando nenhum evento estiver bufferizado para a chave informada.
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
   * Normaliza uma string de status bruta para forma canonica: connected | disconnected | qr | connecting | <original>.
   * Retorna null para valores desconhecidos ou genericos.
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
