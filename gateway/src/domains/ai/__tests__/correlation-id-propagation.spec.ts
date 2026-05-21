import { ConfigService } from '@nestjs/config';
import { AiRunRequestConsumer } from '../consumers/ai-completion.consumer';
import { RedisStreamsService } from '../../../infrastructure/redis/redis-streams.service';
import { AIProviderFactory } from '../providers/ai-provider.factory';
import { AiRunOrchestratorService } from '../services/ai-run-orchestrator.service';
import { GatewayConfigService } from '../../../shared/services/gateway-config.service';
import { OpenAIProviderAdapter } from '../providers/openai/openai-provider.adapter';
import { PromptAssemblerService } from '../services/prompt-assembler.service';
import { ContextWindowService } from '../services/context-window.service';
import { InternalAiClientService } from '../services/internal-ai-client.service';
import { ToolExecutorService } from '../services/tool-executor.service';
import { GuardrailEvaluatorService } from '../services/guardrail-evaluator.service';
import { StreamHandlerService } from '../services/stream-handler.service';
import { AiMetricsService } from '../services/ai-metrics.service';
import { AiCancellationRegistry } from '../ai-cancellation.registry';

describe('correlation_id propagation', () => {
  it('consumer prioritizes payload correlation_id when forwarding to orchestrator', async () => {
    const redisStreams = {
      ensureConsumerGroup: jest.fn().mockResolvedValue(undefined),
      readGroup: jest.fn().mockResolvedValue([]),
      ack: jest.fn().mockResolvedValue(undefined),
      publish: jest.fn().mockResolvedValue(undefined),
      publishResponse: jest.fn().mockResolvedValue(undefined),
      createSuccessResponse: jest.fn().mockReturnValue({}),
      createErrorResponse: jest.fn().mockReturnValue({}),
    } as unknown as jest.Mocked<RedisStreamsService>;

    const providerFactory = {
      getProvider: jest.fn().mockReturnValue({
        complete: jest.fn().mockResolvedValue({ content: 'ok' }),
      }),
    } as unknown as jest.Mocked<AIProviderFactory>;

    const orchestrator = {
      execute: jest.fn().mockResolvedValue({ run_id: 'run-1' }),
    } as unknown as jest.Mocked<AiRunOrchestratorService>;

    const configService = {
      get: jest.fn().mockReturnValue(undefined),
    } as unknown as jest.Mocked<ConfigService>;

    const gatewayConfigService = {
      isTestEnvironment: jest.fn().mockReturnValue(true),
    } as unknown as GatewayConfigService;

    const consumer = new AiRunRequestConsumer(
      configService,
      gatewayConfigService,
      redisStreams,
      providerFactory,
      orchestrator,
    );

    await (
      consumer as unknown as {
        processMessage: (m: unknown) => Promise<void>;
      }
    ).processMessage({
      id: 'msg-1',
      message: {
        correlationId: 'msg-correlation',
        action: 'run',
        provider: 'openai',
        payload: {
          correlation_id: 'payload-correlation',
          tenant_id: 'tenant-1',
          run_id: 'run-1',
        },
      },
    });

    expect(orchestrator.execute).toHaveBeenCalledWith(
      expect.objectContaining({
        correlationId: 'payload-correlation',
      }),
    );
  });

  it('orchestrator returns and streams correlation_id', async () => {
    const openaiProvider = {
      complete: jest.fn().mockResolvedValue({
        content: 'final response',
        model: 'gpt-4o',
        finishReason: 'stop',
        promptTokens: 10,
        completionTokens: 5,
        totalTokens: 15,
      }),
    } as unknown as jest.Mocked<OpenAIProviderAdapter>;

    const promptAssembler = {
      resolvePrompt: jest.fn().mockResolvedValue('prompt'),
    } as unknown as jest.Mocked<PromptAssemblerService>;

    const contextWindow = {
      resolveContext: jest.fn().mockResolvedValue({}),
    } as unknown as jest.Mocked<ContextWindowService>;

    const internalAiClient = {
      fetchToolsCached: jest.fn().mockResolvedValue([]),
    } as unknown as jest.Mocked<InternalAiClientService>;

    const toolExecutor = {
      executeTool: jest.fn(),
    } as unknown as jest.Mocked<ToolExecutorService>;

    const guardrail = {
      evaluateToolCall: jest.fn().mockReturnValue({ allowed: true }),
      evaluateFinalOutput: jest.fn().mockReturnValue({ allowed: true }),
    } as unknown as jest.Mocked<GuardrailEvaluatorService>;

    const streamHandler = {
      emitChunk: jest.fn().mockResolvedValue(undefined),
      emitFinal: jest.fn().mockResolvedValue(undefined),
    } as unknown as jest.Mocked<StreamHandlerService>;

    const aiMetrics = {
      recordRunCompleted: jest.fn(),
      recordTokenUsage: jest.fn(),
      recordRunCost: jest.fn(),
      recordIterationsPerRun: jest.fn(),
      recordTotalTokensPerRun: jest.fn(),
      recordEarlyExit: jest.fn(),
      recordTruncatedResponse: jest.fn(),
      recordSnapshotResolution: jest.fn(),
    } as unknown as jest.Mocked<AiMetricsService>;

    const cancellationRegistry = {
      markCancelled: jest.fn().mockResolvedValue(undefined),
      isCancelled: jest.fn().mockResolvedValue(false),
      clear: jest.fn().mockResolvedValue(undefined),
    } as unknown as jest.Mocked<AiCancellationRegistry>;

    const orchestrator = new AiRunOrchestratorService(
      openaiProvider,
      promptAssembler,
      contextWindow,
      internalAiClient,
      toolExecutor,
      guardrail,
      streamHandler,
      aiMetrics,
      undefined,
      cancellationRegistry,
    );

    const response = await orchestrator.execute({
      correlationId: 'corr-123',
      tenantId: 'tenant-1',
      runId: 'run-1',
      agentId: 'agent-1',
      inputText: 'hello',
      streamingEnabled: true,
      traceId: 'trace-1',
    });

    expect(response['correlation_id']).toBe('corr-123');
    expect(streamHandler.emitFinal).toHaveBeenCalledWith(
      'tenant-1',
      'run-1',
      'final response',
      'trace-1',
      'corr-123',
    );
  });
});
