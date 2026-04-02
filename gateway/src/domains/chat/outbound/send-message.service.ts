import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { ProviderFactory } from '../providers/provider.factory';
import { RetryPolicy } from './retry-policy';
import {
  SendTextRequest,
  SendMediaRequest,
  SendMessageResult,
} from '../contracts/provider.interface';
import {
  CircuitBreakerService,
  CircuitState,
} from '../../../shared/services/circuit-breaker';
import { getCircuitBreakerOptions } from '../../../core/config/circuit-breaker.config';
import {
  OutboundMessage,
  PendingMessage,
  SendResult,
} from '../models/outbound.model';

export type { OutboundMessage, PendingMessage, SendResult };

/**
 * Send Message Service with Circuit Breaker protection.
 *
 * Circuit Breaker Configuration:
 * - Threshold: 5 failures (WhatsApp APIs have rate limits)
 * - Reset Timeout: 30 seconds (quick recovery expected)
 * - Fallback: Queue message for later retry
 */
/**
 * Orquestra envio de mensagens outbound com circuit breaker e fila local de retry.
 */
@Injectable()
export class SendMessageService {
  private readonly logger = new Logger(SendMessageService.name);
  private readonly maxRetries = 3;

  /** In-memory queue for failed messages when circuit is open */
  private readonly retryQueue: PendingMessage[] = [];
  private isRetryQueueProcessing = false;
  private readonly maxQueueSize: number;

  constructor(
    private readonly configService: ConfigService,
    private readonly providerFactory: ProviderFactory,
    private readonly retryPolicy: RetryPolicy,
    private readonly circuitBreaker: CircuitBreakerService,
  ) {
    this.maxQueueSize = Number(
      this.configService.get<string | number>('MESSAGE_RETRY_QUEUE_MAX_SIZE') ??
        '1000',
    );
  }

  /**
   * Envia uma mensagem outbound utilizando o provedor configurado.
   *
   * @param message - Dados da mensagem outbound a enviar
   * @returns Resultado normalizado do envio
   */
  async send(message: OutboundMessage): Promise<SendResult> {
    return this.sendWithRetryCount(message, 0);
  }

  /**
   * Envia mensagem preservando metadados de retry para itens da fila.
   *
   * @param message - Dados da mensagem outbound
   * @param retryCount - Numero de tentativas ja realizadas
   * @returns Resultado normalizado do envio
   */
  private async sendWithRetryCount(
    message: OutboundMessage,
    retryCount: number,
  ): Promise<SendResult> {
    const startTime = Date.now();
    const circuitName = `whatsapp:${message.provider}`;

    this.logger.debug(
      `Sending ${message.type} message to ${message.to} via ${message.provider}`,
    );

    try {
      const options = getCircuitBreakerOptions('whatsapp', {
        name: circuitName,
        onStateChange: (_serviceName, previousState, nextState) => {
          if (
            previousState !== CircuitState.CLOSED &&
            nextState === CircuitState.CLOSED
          ) {
            this.triggerRetryQueueProcessing(circuitName);
          }
        },
      });

      return await this.circuitBreaker.call(
        circuitName,
        () => this.doSend(message, startTime),
        options,
        () => this.queueForRetry(message, startTime, retryCount),
      );
    } catch (error) {
      // If doSend throws and there's no fallback, catch here
      const processingTimeMs = Date.now() - startTime;
      return {
        success: false,
        error: error instanceof Error ? error.message : 'Unknown error',
        attempts: 1,
        processingTimeMs,
      };
    }
  }

  /**
   * Executa o envio efetivo atraves do provedor com politica de retry.
   *
   * @param message - Dados da mensagem outbound
   * @param startTime - Timestamp de inicio para calculo de latencia
   * @returns Resultado normalizado do envio
   */
  private async doSend(
    message: OutboundMessage,
    startTime: number,
  ): Promise<SendResult> {
    const provider = this.providerFactory.getProvider(message.provider);

    const result = await this.retryPolicy.execute<SendMessageResult>(
      async () => {
        if (message.type === 'text') {
          const request: SendTextRequest = {
            to: message.to,
            text: message.text ?? '',
          };
          return provider.sendText(message.instanceToken, request);
        }

        const request: SendMediaRequest = {
          to: message.to,
          type: message.mediaType ?? 'image',
          mediaUrl: message.mediaUrl ?? '',
          caption: message.caption,
          fileName: message.fileName,
        };
        return provider.sendMedia(message.instanceToken, request);
      },
      { maxRetries: 3 },
    );

    const processingTimeMs = Date.now() - startTime;

    if (result.success && result.data?.success) {
      this.logger.log(
        `Message sent successfully: ${result.data.messageId} (${processingTimeMs}ms, ${result.attempts} attempts)`,
      );

      return {
        success: true,
        messageId: result.data.messageId,
        attempts: result.attempts,
        processingTimeMs,
      };
    }

    const errorMessage =
      result.lastError?.message ?? result.data?.error ?? 'Unknown error';

    this.logger.error(
      `Failed to send message after ${result.attempts} attempts: ${errorMessage}`,
    );

    // Throw to trigger circuit breaker
    throw new Error(errorMessage);
  }

  /**
   * Enfileira a mensagem localmente quando o circuito esta aberto.
   *
   * @param message - Dados da mensagem a enfileirar
   * @param startTime - Timestamp de inicio para calculo de latencia
   * @param retryCount - Numero de tentativas ja realizadas
   * @returns Resultado indicando que a mensagem foi enfileirada
   */
  private queueForRetry(
    message: OutboundMessage,
    startTime: number,
    retryCount: number,
  ): Promise<SendResult> {
    const processingTimeMs = Date.now() - startTime;

    if (this.retryQueue.length >= this.maxQueueSize) {
      this.logger.error('Retry queue is full, dropping message', {
        to: message.to,
        correlationId: message.correlationId,
      });

      return Promise.resolve({
        success: false,
        error: 'Circuit open and retry queue full',
        attempts: 0,
        processingTimeMs,
        queued: false,
      });
    }

    this.retryQueue.push({
      message,
      addedAt: Date.now(),
      retryCount,
    });

    this.logger.warn('Circuit open - message queued for retry', {
      to: message.to,
      correlationId: message.correlationId,
      queueSize: this.retryQueue.length,
      retryCount,
    });

    return Promise.resolve({
      success: false,
      error: 'Circuit open - queued for retry',
      attempts: 0,
      processingTimeMs,
      queued: true,
    });
  }

  /**
   * Processa a fila local de retry quando o circuito volta ao estado fechado.
   */
  async processRetryQueue(): Promise<void> {
    if (this.retryQueue.length === 0) {
      return;
    }

    this.logger.log(`Processing ${this.retryQueue.length} queued messages`);

    const messages = [...this.retryQueue];
    this.retryQueue.length = 0;

    for (const pending of messages) {
      try {
        const result = await this.sendWithRetryCount(
          pending.message,
          pending.retryCount,
        );

        if (result.success || result.queued === true) {
          continue;
        }

        if (pending.retryCount < this.maxRetries) {
          pending.retryCount++;
          this.retryQueue.push(pending);
        } else {
          this.logger.error('Message dropped after max retries', {
            correlationId: pending.message.correlationId,
          });
        }
      } catch {
        // If circuit opens again, remaining messages stay in queue
        if (pending.retryCount < this.maxRetries) {
          pending.retryCount++;
          this.retryQueue.push(pending);
        } else {
          this.logger.error('Message dropped after max retries', {
            correlationId: pending.message.correlationId,
          });
        }
      }
    }
  }

  /**
   * Retorna o tamanho atual da fila local de retry.
   */
  getQueueSize(): number {
    return this.retryQueue.length;
  }

  /**
   * Retorna o estado atual do circuit breaker para um provedor.
   *
   * @param provider - Nome do provedor a consultar
   * @returns Nome do estado do circuito ou undefined
   */
  getCircuitState(provider: 'uazapi' | 'zapi'): string | undefined {
    return this.circuitBreaker.getState(`whatsapp:${provider}`);
  }

  /**
   * Inicia o processamento da fila de retry em background ao fechar o circuito.
   *
   * @param circuitName - Nome do circuito que foi fechado
   */
  private triggerRetryQueueProcessing(circuitName: string): void {
    if (this.isRetryQueueProcessing) {
      return;
    }

    this.isRetryQueueProcessing = true;
    void this.processRetryQueue()
      .catch((error) => {
        this.logger.error(
          `Retry queue processing failed for ${circuitName}`,
          error instanceof Error ? error.stack : String(error),
        );
      })
      .finally(() => {
        this.isRetryQueueProcessing = false;
      });
  }
}
