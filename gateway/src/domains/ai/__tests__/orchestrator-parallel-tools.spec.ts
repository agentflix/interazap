import { AiRunOrchestratorService } from '../services/ai-run-orchestrator.service';
import { OpenAIProviderAdapter } from '../providers/openai/openai-provider.adapter';
import { PromptAssemblerService } from '../services/prompt-assembler.service';
import { ContextWindowService } from '../services/context-window.service';
import { InternalAiClientService } from '../services/internal-ai-client.service';
import { ToolExecutorService } from '../services/tool-executor.service';
import { GuardrailEvaluatorService } from '../services/guardrail-evaluator.service';
import { StreamHandlerService } from '../services/stream-handler.service';
import { AiMetricsService } from '../services/ai-metrics.service';
import { AiCancellationRegistry } from '../ai-cancellation.registry';

type CompletionResponse = {
  content: string;
  model: string;
  finishReason: 'stop' | 'length' | 'content_filter' | 'tool_calls' | null;
  promptTokens: number;
  completionTokens: number;
  totalTokens: number;
};

const buildMocks = () => {
  const openaiProvider = {
    complete: jest.fn<Promise<CompletionResponse>, [unknown]>(),
  } as unknown as jest.Mocked<OpenAIProviderAdapter>;

  const promptAssembler = {
    resolvePrompt: jest.fn().mockResolvedValue('base prompt'),
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
    emitChunk: jest.fn(),
    emitFinal: jest.fn(),
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

  return {
    openaiProvider,
    promptAssembler,
    contextWindow,
    internalAiClient,
    toolExecutor,
    guardrail,
    streamHandler,
    aiMetrics,
    cancellationRegistry,
  };
};

const toolCallContent = (calls: Array<Record<string, unknown>>): string =>
  JSON.stringify(calls);

describe('AiRunOrchestratorService parallel tools', () => {
  const baseRequest = {
    correlationId: 'corr-parallel',
    tenantId: 'tenant-1',
    runId: 'run-parallel',
    agentId: 'agent-1',
    inputText: 'hello',
    maxToolIterations: 2,
    runTokenBudget: 1000,
  };

  it('executes 3 tool calls in parallel when each tool takes 50ms', async () => {
    const mocks = buildMocks();
    const service = new AiRunOrchestratorService(
      mocks.openaiProvider,
      mocks.promptAssembler,
      mocks.contextWindow,
      mocks.internalAiClient,
      mocks.toolExecutor,
      mocks.guardrail,
      mocks.streamHandler,
      mocks.aiMetrics,
      undefined,
      mocks.cancellationRegistry,
    );

    mocks.openaiProvider.complete
      .mockResolvedValueOnce({
        content: toolCallContent([
          { name: 'tool_a', arguments: { id: 1 } },
          { name: 'tool_b', arguments: { id: 2 } },
          { name: 'tool_c', arguments: { id: 3 } },
        ]),
        model: 'gpt-4o',
        finishReason: 'tool_calls',
        promptTokens: 10,
        completionTokens: 5,
        totalTokens: 15,
      })
      .mockResolvedValueOnce({
        content: 'final',
        model: 'gpt-4o',
        finishReason: 'stop',
        promptTokens: 10,
        completionTokens: 5,
        totalTokens: 15,
      });

    let activeCalls = 0;
    let maxActiveCalls = 0;

    mocks.toolExecutor.executeTool.mockImplementation(
      async (name: string): Promise<Record<string, unknown>> => {
        activeCalls += 1;
        maxActiveCalls = Math.max(maxActiveCalls, activeCalls);
        await new Promise((resolve) => setTimeout(resolve, 50));
        activeCalls -= 1;
        return { success: true, data: { name } };
      },
    );

    const response = await service.execute(baseRequest);

    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledTimes(3);
    expect(maxActiveCalls).toBe(3);
    expect(response['iterations_count']).toBe(1);
  });

  it('deduplicates calls with the same hash before parallel execution', async () => {
    const mocks = buildMocks();
    const service = new AiRunOrchestratorService(
      mocks.openaiProvider,
      mocks.promptAssembler,
      mocks.contextWindow,
      mocks.internalAiClient,
      mocks.toolExecutor,
      mocks.guardrail,
      mocks.streamHandler,
      mocks.aiMetrics,
      undefined,
      mocks.cancellationRegistry,
    );

    mocks.openaiProvider.complete
      .mockResolvedValueOnce({
        content: toolCallContent([
          { name: 'lookup_customer', arguments: { id: 10, include: true } },
          { name: 'lookup_customer', arguments: { include: true, id: 10 } },
        ]),
        model: 'gpt-4o',
        finishReason: 'tool_calls',
        promptTokens: 10,
        completionTokens: 5,
        totalTokens: 15,
      })
      .mockResolvedValueOnce({
        content: 'final',
        model: 'gpt-4o',
        finishReason: 'stop',
        promptTokens: 10,
        completionTokens: 5,
        totalTokens: 15,
      });

    mocks.toolExecutor.executeTool.mockResolvedValue({
      success: true,
      data: { ok: true },
    });

    const response = await service.execute(baseRequest);

    expect(mocks.guardrail.evaluateToolCall).toHaveBeenCalledTimes(1);
    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledTimes(1);

    const output = response['output'] as Record<string, unknown>;
    const toolCalls = output['tool_calls'] as Array<Record<string, unknown>>;
    expect(toolCalls).toHaveLength(2);
    expect(toolCalls[1]['deduplicated']).toBe(true);
  });

  it('executes only send_message when it appears alongside other tools in the same iteration', async () => {
    const mocks = buildMocks();
    const service = new AiRunOrchestratorService(
      mocks.openaiProvider,
      mocks.promptAssembler,
      mocks.contextWindow,
      mocks.internalAiClient,
      mocks.toolExecutor,
      mocks.guardrail,
      mocks.streamHandler,
      mocks.aiMetrics,
      undefined,
      mocks.cancellationRegistry,
    );

    mocks.openaiProvider.complete.mockResolvedValueOnce({
      content: toolCallContent([
        { name: 'send_message', arguments: { content: 'Hello from AI' } },
        { name: 'lookup_customer', arguments: { id: 99 } },
      ]),
      model: 'gpt-4o',
      finishReason: 'tool_calls',
      promptTokens: 10,
      completionTokens: 5,
      totalTokens: 15,
    });

    mocks.toolExecutor.executeTool.mockResolvedValue({ success: true });

    const response = await service.execute(baseRequest);

    // Only send_message must be executed — lookup_customer must be skipped
    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledTimes(1);
    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledWith(
      'send_message',
      expect.any(Object),
      expect.any(Object),
    );

    const output = response['output'] as Record<string, unknown>;
    const toolCalls = output['tool_calls'] as Array<Record<string, unknown>>;

    const skippedCall = toolCalls.find(
      (tc) => tc['name'] === 'lookup_customer',
    );
    expect(skippedCall).toBeDefined();
    expect(skippedCall?.['skipped_priority_guardrail']).toBe(true);

    // send_message success → early exit, no second LLM call
    expect(response['early_exit_reason']).toBe('send_message_completed');
    expect(mocks.openaiProvider.complete).toHaveBeenCalledTimes(1);
  });

  it('executes only delegate_to_agent when it appears alongside other tools', async () => {
    const mocks = buildMocks();
    const service = new AiRunOrchestratorService(
      mocks.openaiProvider,
      mocks.promptAssembler,
      mocks.contextWindow,
      mocks.internalAiClient,
      mocks.toolExecutor,
      mocks.guardrail,
      mocks.streamHandler,
      mocks.aiMetrics,
      undefined,
      mocks.cancellationRegistry,
    );

    mocks.openaiProvider.complete.mockResolvedValueOnce({
      content: toolCallContent([
        { name: 'lookup_customer', arguments: { id: 1 } },
        {
          name: 'delegate_to_agent',
          arguments: { target_agent_id: 'billing-agent' },
        },
      ]),
      model: 'gpt-4o',
      finishReason: 'tool_calls',
      promptTokens: 10,
      completionTokens: 5,
      totalTokens: 15,
    });

    mocks.toolExecutor.executeTool.mockResolvedValue({
      success: true,
      delegated: true,
    });

    const response = await service.execute(baseRequest);

    // Only delegate_to_agent must be executed
    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledTimes(1);
    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledWith(
      'delegate_to_agent',
      expect.any(Object),
      expect.any(Object),
    );

    const output = response['output'] as Record<string, unknown>;
    const toolCalls = output['tool_calls'] as Array<Record<string, unknown>>;

    const skippedCall = toolCalls.find(
      (tc) => tc['name'] === 'lookup_customer',
    );
    expect(skippedCall?.['skipped_priority_guardrail']).toBe(true);

    expect(response['early_exit_reason']).toBe('delegation_completed');
  });
});
