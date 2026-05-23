import {
  Injectable,
  Logger,
  OnModuleDestroy,
  OnModuleInit,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import {
  RedisStreamsService,
  StreamMessage,
} from '../../../infrastructure/redis/redis-streams.service';
import {
  AIProviderFactory,
  UnknownProviderError,
} from '../providers/ai-provider.factory';
import { AICompletionRequest } from '../interfaces/ai-completion-request.dto';
import { OpenAIProviderError } from '../providers/openai/openai-provider.adapter';
import { AiRunOrchestratorService } from '../services/ai-run-orchestrator.service';
import {
  GatewayResponse,
  GatewayErrorCode,
} from '../../../common/interfaces/gateway-response.interface';
import { createGatewayMessage } from '../../../common/interfaces/gateway-message.interface';
import { GatewayConfigService } from '../../../shared/services/gateway-config.service';
import {
  getBooleanField,
  getMessagesField,
  getNumberField,
  getStringArrayField,
  getStringField,
  parseJsonArray,
  parseJsonObject,
  toRecord,
} from '../../../shared/utils/payload-reader.util';

const REQUEST_STREAM = 'ai.run.request';
const RESPONSE_STREAM_PREFIX = 'ai.run.response';

/** Backoff delay in ms when the consumer loop encounters an unrecoverable error. */
const CONSUMER_RETRY_DELAY_MS = 5000;

/**
 * Redis Streams consumer that processes AI completion requests from the `ai.run.request`
 * stream. Routes messages to either the Orchestrator or a direct AI provider based on
 * message shape. Implements the consumer-group pattern for horizontal scaling.
 */
@Injectable()
export class AiRunRequestConsumer implements OnModuleInit, OnModuleDestroy {
  private readonly logger = new Logger(AiRunRequestConsumer.name);
  private isRunning = false;
  private isShuttingDown = false;
  private readonly consumerGroup: string;
  private readonly consumerName: string;
  private readonly defaultMaxTokens: number;
  private readonly defaultMaxToolIterations: number;
  private readonly defaultRunTokenBudget: number;
  private readonly defaultCompactToolResults: boolean;
  private readonly isTestEnvironment: boolean;
  private readonly concurrency: number;
  private readonly readBatchSize: number;

  /**
   * Initializes consumer configuration from environment variables.
   *
   * @param configService        - NestJS ConfigService for env vars
   * @param gatewayConfigService - GatewayConfigService for test-env detection
   * @param redisStreams         - RedisStreamsService for stream read/write
   * @param providerFactory      - AIProviderFactory for direct provider calls
   * @param orchestrator          - AiRunOrchestratorService for orchestration mode
   */
  constructor(
    private readonly configService: ConfigService,
    private readonly gatewayConfigService: GatewayConfigService,
    private readonly redisStreams: RedisStreamsService,
    private readonly providerFactory: AIProviderFactory,
    private readonly orchestrator: AiRunOrchestratorService,
  ) {
    this.isTestEnvironment = this.gatewayConfigService.isTestEnvironment();
    this.consumerGroup =
      this.configService.get<string>('AI_CONSUMER_GROUP') ?? 'gateway-ai';
    this.consumerName =
      this.configService.get<string>('AI_CONSUMER_NAME') ??
      `consumer-${process.pid}`;
    this.defaultMaxTokens = Number(
      this.configService.get<string | number>('AI_AUTOPILOT_MAX_TOKENS') ?? 800,
    );
    this.defaultMaxToolIterations = Number(
      this.configService.get<string | number>('AI_MAX_TOOL_ITERATIONS') ?? 5,
    );
    this.defaultRunTokenBudget = Number(
      this.configService.get<string | number>('AI_RUN_TOKEN_BUDGET') ?? 32000,
    );
    this.defaultCompactToolResults =
      this.configService.get<string>('AI_COMPACT_TOOL_RESULTS') !== 'false';
    const rawConcurrency = Number(
      this.configService.get<string | number>('AI_CONSUMER_CONCURRENCY') ?? 4,
    );
    this.concurrency =
      Number.isFinite(rawConcurrency) && rawConcurrency > 0
        ? Math.min(Math.floor(rawConcurrency), 32)
        : 4;
    const rawBatch = Number(
      this.configService.get<string | number>('AI_CONSUMER_BATCH_SIZE') ??
        Math.max(this.concurrency * 2, 10),
    );
    this.readBatchSize =
      Number.isFinite(rawBatch) && rawBatch > 0
        ? Math.min(Math.floor(rawBatch), 100)
        : 10;
  }

  /**
   * Initializes the consumer group on module start and kicks off the consume loop.
   * Skipped entirely when running in the test environment.
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
      this.consumeLoop().catch((err: unknown) =>
        this.logger.error(
          'consumeLoop crashed',
          (err as Error)?.stack ?? String(err),
        ),
      );
    } catch (error) {
      this.logger.error(
        'Failed to initialize AI consumer — Redis may be unavailable',
        (error as Error).stack,
      );
    }
  }

  /**
   * Signals the consume loop to stop on module destruction.
   */
  onModuleDestroy(): void {
    this.isShuttingDown = true;
    this.isRunning = false;
  }

  /**
   * Continuously reads messages from the consumer group and processes them.
   * Exits when isRunning becomes false or isShuttingDown is set.
   */
  private async consumeLoop(): Promise<void> {
    while (this.isRunning && !this.isShuttingDown) {
      try {
        const messages = await this.redisStreams.readGroup<AICompletionRequest>(
          REQUEST_STREAM,
          this.consumerGroup,
          this.consumerName,
          this.readBatchSize,
          500,
        );

        if (messages.length === 0) {
          continue;
        }

        await this.processBatchInParallel(messages);
      } catch (error) {
        this.logger.error('Consumer loop error, retrying in 5s', {
          error: error instanceof Error ? error.message : String(error),
        });
        await new Promise((resolve) =>
          setTimeout(resolve, CONSUMER_RETRY_DELAY_MS),
        );
      }
    }
  }

  /**
   * Processes a batch of messages with bounded concurrency. Messages within the
   * batch are handled in parallel up to `this.concurrency`, dramatically reducing
   * end-to-end latency under sustained load (each AI completion is I/O bound and
   * can be safely overlapped).
   */
  private async processBatchInParallel(
    messages: ReadonlyArray<StreamMessage<AICompletionRequest>>,
  ): Promise<void> {
    const queue = [...messages];
    const workerCount = Math.min(this.concurrency, queue.length);

    const worker = async (): Promise<void> => {
      while (queue.length > 0) {
        if (this.isShuttingDown) {
          return;
        }
        const streamMessage = queue.shift();
        if (!streamMessage) {
          return;
        }
        try {
          await this.processMessage(streamMessage);
        } catch (error) {
          this.logger.error(
            `Critical error processing message ${streamMessage.id}`,
            {
              error: error instanceof Error ? error.message : String(error),
            },
          );
          try {
            await this.redisStreams.ack(
              REQUEST_STREAM,
              this.consumerGroup,
              streamMessage.id,
            );
          } catch {
            this.logger.error('Failed to ACK failed message', {
              messageId: streamMessage.id,
            });
          }
        }
      }
    };

    const workers: Promise<void>[] = [];
    for (let i = 0; i < workerCount; i++) {
      workers.push(worker());
    }
    await Promise.all(workers);
  }

  /**
   * Processes a single stream message: routes to orchestrator or direct provider,
   * publishes the response, and acknowledges the message.
   */
  private async processMessage(
    streamMessage: StreamMessage<AICompletionRequest>,
  ): Promise<void> {
    const { id, message } = streamMessage;
    const payloadCorrelationId =
      this.getStringField(message.payload, 'correlation_id') ??
      this.getStringField(message.payload, 'correlationId');
    const correlationId = payloadCorrelationId ?? message.correlationId;
    const startTime = Date.now();

    try {
      if (this.shouldHandleAsOrchestrator(message)) {
        const metadataTenantId =
          typeof message.metadata?.tenantId === 'string' &&
          message.metadata.tenantId.trim() !== ''
            ? message.metadata.tenantId
            : undefined;
        const tenantId =
          this.getStringField(message.payload, 'tenant_id') ?? metadataTenantId;
        const traceId =
          this.getStringField(message.payload, 'trace_id') ??
          this.getStringField(message.payload, 'traceId');
        const hydratedAt =
          this.getStringField(message.payload, 'hydrated_at') ??
          this.getStringField(message.payload, 'hydratedAt');
        const runId =
          this.getStringField(message.payload, 'run_id') ?? correlationId;

        if (!tenantId) {
          const processingTimeMs = Date.now() - startTime;
          await this.publishResponse(
            correlationId,
            this.redisStreams.createErrorResponse(
              correlationId,
              'INVALID_PAYLOAD',
              'tenant_id is required',
              processingTimeMs,
            ),
          );

          await this.redisStreams.ack(REQUEST_STREAM, this.consumerGroup, id);
          return;
        }

        const runResponse = await this.orchestrator.execute({
          correlationId,
          tenantId,
          runId,
          traceId,
          ticketId: this.getStringField(message.payload, 'ticket_id'),
          agentId: this.getStringField(message.payload, 'agent_id'),
          agentRole:
            this.getStringField(message.payload, 'agent_role') ?? undefined,
          delegationDepth: this.getNumberField(
            message.payload,
            'delegation_depth',
          ),
          delegationStack: this.getStringArrayField(
            message.payload,
            'delegation_stack',
          ),
          inputText: this.getStringField(message.payload, 'input_text'),
          model: this.getStringField(message.payload, 'model') ?? undefined,
          streamingEnabled:
            this.getBooleanField(message.payload, 'streaming_enabled') ?? false,
          messages: this.getMessages(message.payload),
          maxTokens:
            this.getNumberField(message.payload, 'max_tokens') ??
            this.defaultMaxTokens,
          maxToolIterations:
            this.getNumberField(message.payload, 'max_tool_iterations') ??
            this.defaultMaxToolIterations,
          runTokenBudget:
            this.getNumberField(message.payload, 'run_token_budget') ??
            this.defaultRunTokenBudget,
          compactToolResults:
            this.getBooleanField(message.payload, 'compact_tool_results') ??
            this.defaultCompactToolResults,
          superPrompt: this.getStringField(message.payload, 'super_prompt'),
          segmentPrompt: this.getStringField(message.payload, 'segment_prompt'),
          planPrompt: this.getStringField(message.payload, 'plan_prompt'),
          agentSystemPrompt: this.getStringField(
            message.payload,
            'agent_system_prompt',
          ),
          agentFilePrompts: this.getStringArrayField(
            message.payload,
            'agent_file_prompts',
          ),
          promptSnapshot: this.getPromptSnapshot(message.payload, hydratedAt),
          contextSnapshot: this.getContextSnapshot(message.payload, hydratedAt),
          toolsSnapshot: this.getToolsSnapshot(message.payload),
        });

        await this.redisStreams.publish(
          RESPONSE_STREAM_PREFIX,
          createGatewayMessage({
            correlationId,
            domain: 'ai',
            action: 'run.response',
            provider: message.provider || 'openai',
            payload: runResponse,
          }),
        );

        await this.redisStreams.ack(REQUEST_STREAM, this.consumerGroup, id);
        return;
      }

      const provider = this.providerFactory.getProvider(message.provider);
      const result = await provider.complete(message.payload);
      const processingTimeMs = Date.now() - startTime;

      await this.publishResponse(
        correlationId,
        this.redisStreams.createSuccessResponse(
          correlationId,
          result,
          processingTimeMs,
        ),
      );

      await this.redisStreams.ack(REQUEST_STREAM, this.consumerGroup, id);
    } catch (error) {
      const processingTimeMs = Date.now() - startTime;
      const { code, errorMessage } = this.mapError(error);

      await this.publishResponse(
        correlationId,
        this.redisStreams.createErrorResponse(
          correlationId,
          code,
          errorMessage,
          processingTimeMs,
        ),
      );

      await this.redisStreams.ack(REQUEST_STREAM, this.consumerGroup, id);
      this.logger.error(
        `AI completion [${correlationId}] failed in ${processingTimeMs}ms: ${code} - ${errorMessage}`,
      );
    }
  }

  /**
   * Publishes a structured GatewayResponse to the response stream for the given correlationId.
   */
  private async publishResponse<T>(
    correlationId: string,
    response: GatewayResponse<T>,
  ): Promise<void> {
    const responseStream = `${RESPONSE_STREAM_PREFIX}:${correlationId}`;
    await this.redisStreams.publishResponse(responseStream, response);
  }

  /**
   * Maps an unknown error to a GatewayErrorCode and human-readable message.
   */
  private mapError(error: unknown): {
    code: GatewayErrorCode;
    errorMessage: string;
  } {
    if (error instanceof UnknownProviderError) {
      return {
        code: 'UNKNOWN_PROVIDER',
        errorMessage: error.message,
      };
    }

    if (error instanceof OpenAIProviderError) {
      return {
        code: error.code,
        errorMessage: error.message,
      };
    }

    return {
      code: 'INTERNAL_ERROR',
      errorMessage: (error as Error).message ?? 'Unknown error',
    };
  }

  /**
   * Determines whether the message should be handled by the orchestrator
   * rather than a direct AI provider.
   */
  private shouldHandleAsOrchestrator(
    message: StreamMessage<AICompletionRequest>['message'],
  ): boolean {
    if (message.action === 'run' || message.action === 'orchestrate') {
      return true;
    }

    const payload = this.toRecord(message.payload);
    return (
      typeof payload['run_id'] === 'string' ||
      typeof payload['tenant_id'] === 'string' ||
      message.action === 'ai.run.request' ||
      message.action === ''
    );
  }

  /** Delegates to payload-reader utility for string fields. */
  private getStringField(payload: unknown, key: string): string | undefined {
    return getStringField(payload, key);
  }

  /** Delegates to payload-reader utility for numeric fields. */
  private getNumberField(payload: unknown, key: string): number | undefined {
    return getNumberField(payload, key);
  }

  /** Delegates to payload-reader utility for string-array fields. */
  private getStringArrayField(
    payload: unknown,
    key: string,
  ): string[] | undefined {
    return getStringArrayField(payload, key);
  }

  /** Delegates to payload-reader utility for boolean fields. */
  private getBooleanField(payload: unknown, key: string): boolean | undefined {
    return getBooleanField(payload, key);
  }

  /** Delegates to payload-reader utility for the messages array. */
  private getMessages(
    payload: unknown,
  ):
    | Array<{ role: 'system' | 'user' | 'assistant'; content: string }>
    | undefined {
    return getMessagesField(payload);
  }

  /** Delegates to payload-reader utility for object coercion. */
  private toRecord(payload: unknown): Record<string, unknown> {
    return toRecord(payload);
  }

  /** Delegates to payload-reader utility for JSON array parsing. */
  private parseJsonArray(value: unknown): unknown {
    return parseJsonArray(value);
  }

  /** Delegates to payload-reader utility for JSON object parsing. */
  private parseJsonObject(value: unknown): Record<string, unknown> | undefined {
    return parseJsonObject(value);
  }

  /**
   * Extracts a prompt snapshot from the payload, returning undefined when absent.
   */
  private getPromptSnapshot(
    payload: unknown,
    hydratedAt?: string,
  ):
    | {
        prompt?: string;
        hydrated_at?: string;
      }
    | undefined {
    const prompt = this.getStringField(payload, 'prompt');
    if (!prompt) {
      return undefined;
    }

    return {
      prompt,
      hydrated_at: hydratedAt,
    };
  }

  /**
   * Extracts a context snapshot from the payload, returning undefined when absent.
   */
  private getContextSnapshot(
    payload: unknown,
    hydratedAt?: string,
  ):
    | {
        context?: Record<string, unknown>;
        hydrated_at?: string;
      }
    | undefined {
    const context = this.parseJsonObject(this.toRecord(payload)['context']);
    if (!context) {
      return undefined;
    }

    return {
      context,
      hydrated_at: hydratedAt,
    };
  }

  /**
   * Extracts a tools snapshot from the payload, supporting both object and
   * string-array formats, and normalizing string names to full tool descriptors.
   */
  private getToolsSnapshot(
    payload: unknown,
  ): Array<Record<string, unknown>> | undefined {
    const record = this.toRecord(payload);

    // Primary field: 'tools' — accepts objects (current format) and strings (names only)
    const rawTools = this.parseJsonArray(record['tools']);
    if (Array.isArray(rawTools)) {
      const normalized = rawTools
        .map((item): Record<string, unknown> | null => {
          if (typeof item === 'string' && item.trim() !== '') {
            return { name: item.trim(), description: '', parameters: {} };
          }
          if (
            Boolean(item) &&
            typeof item === 'object' &&
            !Array.isArray(item)
          ) {
            return item as Record<string, unknown>;
          }
          return null;
        })
        .filter((item): item is Record<string, unknown> => item !== null);
      if (normalized.length > 0) {
        return normalized;
      }
    }

    // Fallback field: 'tool_names_snapshot' — array of string names
    const rawNames = this.parseJsonArray(record['tool_names_snapshot']);
    if (Array.isArray(rawNames)) {
      const normalized = rawNames
        .filter(
          (item): item is string =>
            typeof item === 'string' && item.trim() !== '',
        )
        .map(
          (name): Record<string, unknown> => ({
            name: name.trim(),
            description: '',
            parameters: {},
          }),
        );
      if (normalized.length > 0) {
        return normalized;
      }
    }

    return undefined;
  }
}
