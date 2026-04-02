import {
  Injectable,
  Logger,
  OnModuleInit,
  OnModuleDestroy,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { StreamDlqService } from '../../../shared/services/queue/stream-dlq.service';
import { GatewayConfigService } from '../../../shared/services/gateway-config.service';
import { SendMessageService } from './send-message.service';
import { OutboundMessage, SendResult } from '../models/outbound.model';
import {
  calculateRetryDelay,
  getQueueConfig,
} from '../../../shared/services/queue/queue-resilience.config';
import { RedisStreamMessage } from '../../../infrastructure/redis/redis.service';

// Blocking read configuration
const BLOCK_TIMEOUT_MS = 5000;
const BATCH_SIZE = 10;

/**
 * Consome mensagens outbound do Redis Stream e publica status de envio.
 */
@Injectable()
export class SendMessageConsumer implements OnModuleInit, OnModuleDestroy {
  private readonly logger = new Logger(SendMessageConsumer.name);
  private isRunning = false;
  private isShuttingDown = false;
  private isProcessing = false;
  private readonly pendingTimeouts = new Set<NodeJS.Timeout>();
  private readonly consumerGroup = 'gateway-outbound';
  private readonly consumerName: string;
  private readonly isTestEnvironment: boolean;
  private readonly streamName: string;
  private readonly resultStream: string;
  private readonly queueConfig: ReturnType<typeof getQueueConfig>;

  constructor(
    private readonly configService: ConfigService,
    private readonly gatewayConfigService: GatewayConfigService,
    private readonly redisService: RedisService,
    private readonly sendMessageService: SendMessageService,
    private readonly streamDlqService: StreamDlqService,
  ) {
    this.isTestEnvironment = this.gatewayConfigService.isTestEnvironment();
    this.streamName = this.gatewayConfigService.chatOutboundStream;
    this.resultStream = this.gatewayConfigService.chatOutboundStatusStream;
    this.queueConfig = getQueueConfig(this.streamName);
    this.consumerName =
      this.configService.get<string>('SEND_MESSAGE_CONSUMER_NAME') ??
      `gateway-${process.pid}`;
  }

  /**
   * Inicializa o consumer de outbound quando habilitado por configuracao.
   */
  async onModuleInit(): Promise<void> {
    if (!this.shouldStartConsumers()) {
      this.logger.log('Outbound message consumer disabled by configuration');
      return;
    }

    try {
      await this.ensureConsumerGroup();
      this.startConsuming();
    } catch (error) {
      this.logger.error(
        'Failed to initialize outbound consumer — Redis may be unavailable',
        (error as Error).stack,
      );
    }
  }

  /**
   * Executa desligamento gracioso aguardando processamento pendente.
   */
  async onModuleDestroy(): Promise<void> {
    this.logger.log('Graceful shutdown initiated...');
    this.isShuttingDown = true;
    this.isRunning = false;

    for (const timeout of this.pendingTimeouts) {
      clearTimeout(timeout);
    }
    this.pendingTimeouts.clear();

    await this.waitForProcessing();

    this.logger.log('Outbound message consumer stopped');
  }

  /**
   * Garante que o consumer group Redis existe, criando-o caso necessario.
   */
  private async ensureConsumerGroup(): Promise<void> {
    try {
      const client = this.redisService.getClient();
      await client.xgroup(
        'CREATE',
        this.streamName,
        this.consumerGroup,
        '0',
        'MKSTREAM',
      );
      this.logger.log(
        `Consumer group ${this.consumerGroup} created for ${this.streamName}`,
      );
    } catch (error) {
      // Group already exists - this is fine
      if ((error as Error).message?.includes('BUSYGROUP')) {
        this.logger.debug(
          `Consumer group ${this.consumerGroup} already exists`,
        );
        return;
      }
      this.logger.error('Failed to create consumer group', error);
    }
  }

  /**
   * Inicia o loop de consumo de mensagens.
   */
  private startConsuming(): void {
    if (this.isRunning) return;

    this.isRunning = true;
    this.logger.log(`Starting outbound message consumer: ${this.consumerName}`);

    void this.consumeLoop();
  }

  /**
   * Loop principal de consumo que le e processa mensagens enquanto ativo.
   */
  private async consumeLoop(): Promise<void> {
    while (this.isRunning && !this.isShuttingDown) {
      try {
        const messages = await this.blockingRead();

        this.isProcessing = messages.length > 0;

        for (const message of messages) {
          if (this.isShuttingDown) break;
          await this.processMessageWithRetry(message);
        }

        this.isProcessing = false;
      } catch (error) {
        if (!this.isShuttingDown) {
          this.logger.error('Error in consume loop', (error as Error).stack);
          await this.sleep(1000); // Back off on error
        }
        this.isProcessing = false;
      }
    }
  }

  /**
   * Executa leitura bloqueante via XREADGROUP com BLOCK para eficiencia.
   *
   * @returns Array de mensagens lidas do stream
   */
  private async blockingRead(): Promise<RedisStreamMessage[]> {
    try {
      return await this.redisService.xreadBlock(
        this.streamName,
        this.consumerGroup,
        this.consumerName,
        BLOCK_TIMEOUT_MS,
        BATCH_SIZE,
      );
    } catch (error) {
      // Don't log timeout errors during shutdown
      if (!this.isShuttingDown) {
        this.logger.error('Failed to read messages from stream', error);
      }
      return [];
    }
  }

  /**
   * Processa uma mensagem com logica de retry e envio para DLQ em caso de esgotamento.
   *
   * @param message - Mensagem do Redis Stream a processar
   */
  private async processMessageWithRetry(
    message: RedisStreamMessage,
  ): Promise<void> {
    const startTime = Date.now();
    this.logger.debug(`Processing message ${message.id}`);
    const outboundMessage = this.parseOutboundMessage(message.fields);
    const retryCount = this.getRetryCount(message.fields);

    try {
      const result = await this.sendMessageService.send(outboundMessage);

      if (!result.success) {
        throw new Error(result.error ?? 'Failed to send message');
      }

      await this.publishResult(message.id, outboundMessage, result);
      await this.acknowledge(message.id);

      this.logger.log(
        `Message ${message.id} processed in ${Date.now() - startTime}ms - SUCCESS`,
      );
      return;
    } catch (error) {
      const errorMessage =
        error instanceof Error ? error.message : 'Unknown error';
      const nextRetry = retryCount + 1;

      this.logger.error('Failed to send message', {
        messageId: message.id,
        retryCount,
        error: errorMessage,
      });

      if (nextRetry <= this.queueConfig.retry.maxAttempts) {
        this.requeueWithDelay(message.fields, nextRetry);
      } else {
        await this.streamDlqService.capture(
          this.streamName,
          message.id,
          message.fields,
          errorMessage,
          nextRetry,
          this.queueConfig.dlq,
        );

        await this.publishResult(message.id, outboundMessage, {
          success: false,
          error: errorMessage,
          attempts: nextRetry,
          processingTimeMs: Date.now() - startTime,
        });
      }

      await this.acknowledge(message.id);
    }
  }

  /**
   * Reenfileira uma mensagem com atraso calculado pelo backoff de retry.
   *
   * @param fields - Campos da mensagem original
   * @param retryCount - Numero da proxima tentativa
   */
  private requeueWithDelay(
    fields: Record<string, string>,
    retryCount: number,
  ): void {
    const delay = calculateRetryDelay(retryCount, this.queueConfig.retry);
    const timeout = setTimeout(() => {
      this.pendingTimeouts.delete(timeout);
      void this.redisService
        .publishStream(this.streamName, {
          ...fields,
          _retry_count: String(retryCount),
          _requeued_at: new Date().toISOString(),
        })
        .catch((error) =>
          this.logger.error('Failed to requeue message', error),
        );
    }, delay);

    this.pendingTimeouts.add(timeout);
  }

  /**
   * Extrai o contador de retentativas dos campos da mensagem.
   *
   * @param fields - Campos da mensagem do Redis Stream
   * @returns Contador de retentativas ou zero quando ausente
   */
  private getRetryCount(fields: Record<string, string>): number {
    const retryValue = fields._retry_count ?? fields.retry_count ?? '0';
    const parsed = parseInt(retryValue, 10);
    return Number.isFinite(parsed) ? parsed : 0;
  }

  /**
   * Desserializa os campos do Redis Stream para um OutboundMessage tipado.
   *
   * @param fields - Campos da mensagem do Redis Stream
   * @returns Mensagem outbound tipada
   */
  private parseOutboundMessage(
    fields: Record<string, string>,
  ): OutboundMessage {
    return {
      tenantId: fields.tenant_id ?? '',
      instanceId: fields.instance_id ?? '',
      provider: (fields.provider as 'uazapi' | 'zapi') ?? 'uazapi',
      instanceToken: fields.instance_token ?? '',
      type: (fields.type as 'text' | 'media') ?? 'text',
      to: fields.to ?? '',
      text: fields.text,
      mediaType: fields.media_type as
        | 'image'
        | 'video'
        | 'audio'
        | 'document'
        | undefined,
      mediaUrl: fields.media_url,
      caption: fields.caption,
      fileName: fields.file_name,
      correlationId: fields.correlation_id,
    };
  }

  /**
   * Publica o resultado do envio no Redis Stream de status outbound.
   *
   * @param originalMessageId - ID da mensagem original no stream
   * @param message - Mensagem outbound processada
   * @param result - Resultado do envio
   */
  private async publishResult(
    originalMessageId: string,
    message: OutboundMessage,
    result: SendResult,
  ): Promise<void> {
    await this.redisService.publishStream(this.resultStream, {
      original_message_id: originalMessageId,
      tenant_id: message.tenantId,
      instance_id: message.instanceId,
      to: message.to,
      correlation_id: message.correlationId ?? '',
      success: result.success,
      message_id: result.messageId ?? '',
      error: result.error ?? '',
      attempts: result.attempts,
      processing_time_ms: result.processingTimeMs,
      timestamp: new Date().toISOString(),
    });
  }

  /**
   * Confirma o processamento da mensagem no consumer group do Redis.
   *
   * @param messageId - ID da mensagem a confirmar
   */
  private async acknowledge(messageId: string): Promise<void> {
    const client = this.redisService.getClient();
    await client.xack(this.streamName, this.consumerGroup, messageId);
  }

  /**
   * Aguarda o processamento pendente ser concluido com timeout de 10 segundos.
   */
  private async waitForProcessing(): Promise<void> {
    const timeout = 10000;
    const start = Date.now();

    while (this.isProcessing && Date.now() - start < timeout) {
      await this.sleep(100);
    }
  }

  /**
   * Determina se os consumers devem iniciar baseado em variaveis de ambiente.
   *
   * @returns true quando os consumers devem ser iniciados
   */
  private shouldStartConsumers(): boolean {
    const override = this.configService.get<string>('CONSUMERS_ENABLED');
    if (override !== undefined) {
      return override === 'true';
    }

    if (this.isTestEnvironment) {
      return false;
    }

    return true;
  }

  /**
   * Aguarda o numero de milissegundos informado.
   *
   * @param ms - Tempo de espera em milissegundos
   */
  private sleep(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }
}
