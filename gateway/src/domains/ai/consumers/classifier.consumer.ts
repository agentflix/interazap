import {
  Injectable,
  Logger,
  OnModuleDestroy,
  OnModuleInit,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { RedisStreamsService } from '../../../infrastructure/redis/redis-streams.service';
import { OpenAIProviderAdapter } from '../providers/openai/openai-provider.adapter';
import { createGatewayMessage } from '../../../common/interfaces/gateway-message.interface';
import { GatewayConfigService } from '../../../shared/services/gateway-config.service';
import { MetricsService } from '../../../metrics/metrics.service';
import type {
  ClassifierDecision,
  ClassifierRequestPayload,
} from '../models/classifier.model';

const REQUEST_STREAM = 'ai.classify.request';

/**
 * Consumer responsável por classificar mensagens recebidas antes de iniciar uma run de AI.
 */
@Injectable()
export class ClassifierConsumer implements OnModuleInit, OnModuleDestroy {
  private readonly logger = new Logger(ClassifierConsumer.name);
  private isRunning = false;
  private readonly consumerGroup: string;
  private readonly consumerName: string;
  private readonly isTestEnvironment: boolean;

  constructor(
    private readonly configService: ConfigService,
    private readonly gatewayConfigService: GatewayConfigService,
    private readonly redisStreams: RedisStreamsService,
    private readonly openaiProvider: OpenAIProviderAdapter,
    private readonly metricsService: MetricsService,
  ) {
    this.isTestEnvironment = this.gatewayConfigService.isTestEnvironment();
    this.consumerGroup =
      this.configService.get<string>('AI_CLASSIFIER_GROUP') ??
      'gateway-ai-classifier';
    this.consumerName =
      this.configService.get<string>('AI_CLASSIFIER_CONSUMER') ??
      `classifier-${process.pid}`;
  }

  /**
   * Inicializa o consumer e inicia o loop de leitura do stream quando o módulo sobe.
   * @returns Promise resolvida após garantir o consumer group e disparar o loop assíncrono.
   */
  async onModuleInit(): Promise<void> {
    if (this.isTestEnvironment) {
      return;
    }

    try {
      await this.redisStreams.ensureConsumerGroup(
        REQUEST_STREAM,
        this.consumerGroup,
      );
      this.isRunning = true;
      void this.consumeLoop();
    } catch (error) {
      this.logger.error(
        'Failed to initialize classifier consumer — Redis may be unavailable',
        (error as Error).stack,
      );
    }
  }

  /**
   * Interrompe o loop de consumo quando o módulo é destruído.
   * @returns Não retorna valor.
   */
  onModuleDestroy(): void {
    this.isRunning = false;
  }

  /**
   * Loop principal de consumo que lê mensagens do stream em intervalos contínuos.
   * @returns Promise que resolve apenas quando o consumer é interrompido.
   */
  private async consumeLoop(): Promise<void> {
    while (this.isRunning) {
      const messages =
        await this.redisStreams.readGroup<ClassifierRequestPayload>(
          REQUEST_STREAM,
          this.consumerGroup,
          this.consumerName,
          10,
          500,
        );

      for (const streamMessage of messages) {
        await this.processMessage(
          streamMessage.id,
          streamMessage.message.payload,
        );
      }
    }
  }

  /**
   * Processa uma única mensagem do stream classificando e emitindo na fila de runs.
   * @param messageId - Identificador da mensagem no stream Redis.
   * @param payload - Payload desserializado da mensagem recebida.
   * @returns Promise resolvida após classificação, publicação e ACK da mensagem.
   */
  private async processMessage(
    messageId: string,
    payload: ClassifierRequestPayload,
  ): Promise<void> {
    const tenantId = String(payload.tenant_id ?? '');
    const inputText = String(payload.input_text ?? payload.body ?? '');
    const runId = String(payload.run_id ?? '');

    const decision = await this.classify(inputText);
    this.metricsService.recordClassifierDecision(decision);

    if (decision === 'RESPOND') {
      await this.redisStreams.publish(
        'ai.run.request',
        createGatewayMessage({
          correlationId: runId || `${Date.now()}-${Math.random()}`,
          domain: 'ai',
          action: 'run',
          provider: 'openai',
          payload: {
            ...payload,
            classifier_result: decision,
            tenant_id: tenantId,
            run_id: runId,
          },
        }),
      );
    }

    await this.redisStreams.ack(REQUEST_STREAM, this.consumerGroup, messageId);
  }

  /**
   * Classifica um texto usando o provider de AI e retorna a decisão correspondente.
   * @param text - Texto a ser classificado pelo modelo de linguagem.
   * @returns Decisão de classificação retornada pelo modelo ou `RESPOND` como fallback.
   */
  private async classify(text: string): Promise<ClassifierDecision> {
    if (text.trim() === '') {
      return 'SKIP';
    }

    const completion = await this.openaiProvider.complete({
      model: 'gpt-4o-mini',
      temperature: 0,
      maxTokens: 20,
      messages: [
        {
          role: 'system',
          content:
            'Classify the message with one label only: RESPOND, SKIP, DEBOUNCE, HUMAN_ONLY.',
        },
        {
          role: 'user',
          content: text,
        },
      ],
    });

    const label = completion.content.trim().toUpperCase();
    if (label.includes('HUMAN_ONLY')) return 'HUMAN_ONLY';
    if (label.includes('DEBOUNCE')) return 'DEBOUNCE';
    if (label.includes('SKIP')) return 'SKIP';
    if (label.includes('RESPOND')) return 'RESPOND';

    this.logger.warn(
      `Unknown classifier label: ${label}; defaulting to RESPOND`,
    );
    return 'RESPOND';
  }
}
