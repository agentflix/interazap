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
    resolveContext: jest.fn().mockResolvedValue({ ticket: 'ctx' }),
  } as unknown as jest.Mocked<ContextWindowService>;

  const internalAiClient = {
    fetchTools: jest.fn().mockResolvedValue([
      {
        type: 'function',
        function: {
          name: 'lookup_customer',
          description: 'Lookup customer info',
          parameters: { type: 'object', properties: {} },
        },
      },
      {
        type: 'function',
        function: {
          name: 'send_message',
          description: 'Send final message',
          parameters: { type: 'object', properties: {} },
        },
      },
    ]),
    fetchToolsCached: jest.fn().mockResolvedValue([
      {
        type: 'function',
        function: {
          name: 'lookup_customer',
          description: 'Lookup customer info',
          parameters: { type: 'object', properties: {} },
        },
      },
    ]),
  } as unknown as jest.Mocked<InternalAiClientService>;

  const toolExecutor = {
    executeTool: jest.fn().mockResolvedValue({
      success: true,
      data: { ok: true },
    }),
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

describe('Cost control orchestrator (PRD-AI-004 phase 2)', () => {
  const baseRequest = {
    correlationId: 'corr-1',
    tenantId: 'tenant-1',
    runId: 'run-1',
    agentId: 'agent-1',
    inputText: 'hello',
    ticketId: 'ticket-1',
  };

  it('stops loop at maxToolIterations and returns iteration metrics', async () => {
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
          { name: 'lookup_customer', arguments: { id: 1 } },
        ]),
        model: 'gpt-4o',
        finishReason: 'tool_calls',
        promptTokens: 10,
        completionTokens: 5,
        totalTokens: 15,
      })
      .mockResolvedValueOnce({
        content: toolCallContent([
          { name: 'lookup_customer', arguments: { id: 2 } },
        ]),
        model: 'gpt-4o',
        finishReason: 'tool_calls',
        promptTokens: 10,
        completionTokens: 5,
        totalTokens: 15,
      })
      .mockResolvedValueOnce({
        content: toolCallContent([
          { name: 'lookup_customer', arguments: { id: 3 } },
        ]),
        model: 'gpt-4o',
        finishReason: 'tool_calls',
        promptTokens: 10,
        completionTokens: 5,
        totalTokens: 15,
      })
      .mockResolvedValueOnce({
        content: toolCallContent([
          { name: 'lookup_customer', arguments: { id: 4 } },
        ]),
        model: 'gpt-4o',
        finishReason: 'tool_calls',
        promptTokens: 10,
        completionTokens: 5,
        totalTokens: 15,
      })
      // Fallback completion without tools (text-only)
      .mockResolvedValueOnce({
        content: 'Fallback text response',
        model: 'gpt-4o',
        finishReason: 'stop',
        promptTokens: 15,
        completionTokens: 8,
        totalTokens: 23,
      });

    const response = await service.execute({
      ...baseRequest,
      maxToolIterations: 3,
      runTokenBudget: 1000,
    });

    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledTimes(4);
    expect(response['iterations_count']).toBe(3);
    expect(response['early_exit_reason']).toBe('max_iterations_fallback');
    // 15*4 (loop completions) + 23 (fallback) = 83
    expect(response['total_tokens_used']).toBe(83);
    expect(mocks.aiMetrics.recordIterationsPerRun).toHaveBeenCalledWith(
      'agent-1',
      3,
    );
    expect(mocks.aiMetrics.recordTotalTokensPerRun).toHaveBeenCalledWith(
      'agent-1',
      'gpt-4o',
      83,
    );
    expect(mocks.aiMetrics.recordEarlyExit).toHaveBeenCalledWith(
      'agent-1',
      'max_iterations_fallback',
    );
  });

  it('exits early when send_message succeeds', async () => {
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
        {
          name: 'send_message',
          arguments: { text: 'done' },
        },
      ]),
      model: 'gpt-4o',
      finishReason: 'tool_calls',
      promptTokens: 12,
      completionTokens: 6,
      totalTokens: 18,
    });

    mocks.toolExecutor.executeTool.mockResolvedValueOnce({
      success: true,
      data: { provider_message_id: 'p-1' },
    });

    const response = await service.execute({
      ...baseRequest,
      maxToolIterations: 5,
      runTokenBudget: 1000,
    });

    expect(mocks.openaiProvider.complete).toHaveBeenCalledTimes(1);
    expect(response['early_exit_reason']).toBe('send_message_completed');
    expect(mocks.aiMetrics.recordEarlyExit).toHaveBeenCalledWith(
      'agent-1',
      'send_message_completed',
    );
  });

  it('stops when run token budget is exceeded', async () => {
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
      ]),
      model: 'gpt-4o',
      finishReason: 'tool_calls',
      promptTokens: 40,
      completionTokens: 20,
      totalTokens: 100,
    });

    const response = await service.execute({
      ...baseRequest,
      runTokenBudget: 100,
      maxToolIterations: 5,
    });

    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledTimes(1);
    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledWith(
      'send_message',
      expect.any(Object),
      expect.objectContaining({
        runId: 'run-1',
        tenantId: 'tenant-1',
      }),
    );
    expect(response['early_exit_reason']).toBe('token_budget_exceeded');
    expect(response['iterations_count']).toBe(1);
    expect(mocks.aiMetrics.recordIterationsPerRun).toHaveBeenCalledWith(
      'agent-1',
      1,
    );
    expect(mocks.aiMetrics.recordTotalTokensPerRun).toHaveBeenCalledWith(
      'agent-1',
      'gpt-4o',
      100,
    );
    expect(mocks.aiMetrics.recordEarlyExit).toHaveBeenCalledWith(
      'agent-1',
      'token_budget_exceeded',
    );
  });

  it('executes only send_message when budget is exceeded before first iteration', async () => {
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
        { name: 'send_message', arguments: { text: 'ready' } },
      ]),
      model: 'gpt-4o',
      finishReason: 'tool_calls',
      promptTokens: 40,
      completionTokens: 20,
      totalTokens: 100,
    });

    mocks.toolExecutor.executeTool.mockResolvedValueOnce({
      success: true,
      data: { provider_message_id: 'p-2' },
    });

    const response = await service.execute({
      ...baseRequest,
      runTokenBudget: 100,
      maxToolIterations: 5,
    });

    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledTimes(1);
    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledWith(
      'send_message',
      { text: 'ready' },
      expect.objectContaining({
        tenantId: 'tenant-1',
        runId: 'run-1',
        agentId: 'agent-1',
      }),
    );
    expect(response['early_exit_reason']).toBe('send_message_completed');
    expect(response['iterations_count']).toBe(1);
    expect(mocks.aiMetrics.recordEarlyExit).toHaveBeenCalledWith(
      'agent-1',
      'send_message_completed',
    );
  });

  it('deduplicates identical tool calls in the same run', async () => {
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
          { name: 'lookup_customer', arguments: { id: 10 } },
          { name: 'lookup_customer', arguments: { id: 10 } },
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

    const response = await service.execute({
      ...baseRequest,
      maxToolIterations: 1,
      runTokenBudget: 200,
    });

    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledTimes(2);

    const output = response['output'] as Record<string, unknown>;
    const toolCalls = output['tool_calls'] as Array<Record<string, unknown>>;
    expect(toolCalls.length).toBe(3);
    expect(toolCalls[1]['deduplicated']).toBe(true);
    expect(toolCalls[2]['implicit']).toBe(true);
  });

  it('compacts tool result before context reinjection', async () => {
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

    const longText = 'x'.repeat(2000);

    mocks.openaiProvider.complete
      .mockResolvedValueOnce({
        content: toolCallContent([
          { name: 'lookup_customer', arguments: { id: 1 } },
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

    mocks.toolExecutor.executeTool.mockResolvedValueOnce({
      success: true,
      data: { blob: longText },
    });

    await service.execute({
      ...baseRequest,
      maxToolIterations: 2,
      runTokenBudget: 1000,
      compactToolResults: true,
    });

    const secondCallArg = (mocks.openaiProvider.complete as jest.Mock).mock
      .calls[1][0] as {
      messages: Array<{ role: string; content: string }>;
    };

    const reinjected = secondCallArg.messages.find(
      (message) =>
        message.role === 'system' && message.content.startsWith('tool_result:'),
    );

    expect(reinjected).toBeDefined();
    expect(reinjected?.content).toContain('...[truncated]');
  });

  it('uses fallback limits and preserves composed prompt order on first call', async () => {
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
      content: 'final',
      model: 'gpt-4o',
      finishReason: 'stop',
      promptTokens: 8,
      completionTokens: 4,
      totalTokens: 12,
    });

    await service.execute({
      ...baseRequest,
      superPrompt: 'super',
      segmentPrompt: 'segment',
      planPrompt: 'plan',
      agentSystemPrompt: 'agent-system',
      agentFilePrompts: ['file-a', 'file-b'],
      messages: [{ role: 'user', content: 'existing user message' }],
    });

    const firstCallArg = (mocks.openaiProvider.complete as jest.Mock).mock
      .calls[0][0] as {
      maxTokens?: number;
      messages: Array<{ role: string; content: string }>;
    };

    expect(firstCallArg.maxTokens).toBe(800);
    expect(firstCallArg.messages[0].role).toBe('system');
    expect(firstCallArg.messages[0].content).toBe(
      'super\n\nsegment\n\nplan\n\nagent-system\n\nfile-a\n\nfile-b\n\nbase prompt',
    );
  });

  it('does not hardcode a model when request.model is absent', async () => {
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
      content: 'final',
      model: 'gpt-4o-mini',
      finishReason: 'stop',
      promptTokens: 8,
      completionTokens: 4,
      totalTokens: 12,
    });

    await service.execute({
      ...baseRequest,
    });

    const firstCallArg = (mocks.openaiProvider.complete as jest.Mock).mock
      .calls[0][0] as {
      model?: string;
    };

    expect(firstCallArg).not.toHaveProperty('model');
  });

  it('does not re-execute prompt when messages already contain composed prompt', async () => {
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
      content: 'final',
      model: 'gpt-4o',
      finishReason: 'stop',
      promptTokens: 5,
      completionTokens: 3,
      totalTokens: 8,
    });

    // When the messages array already contains the resolved base prompt as system message,
    // the orchestrator should NOT prepend it again (alreadyContainsPrompt branch).
    await service.execute({
      ...baseRequest,
      messages: [
        { role: 'system', content: 'base prompt' },
        { role: 'user', content: 'hi' },
      ],
    });

    const firstCallArg = (mocks.openaiProvider.complete as jest.Mock).mock
      .calls[0][0] as {
      messages: Array<{ role: string; content: string }>;
    };

    // Only the 2 provided messages, not 3 (no extra system prepend)
    expect(firstCallArg.messages).toHaveLength(2);
    expect(firstCallArg.messages[0]).toEqual({
      role: 'system',
      content: 'base prompt',
    });
  });

  it('does not duplicate tier prompts when they are already in the resolved base prompt', async () => {
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

    mocks.promptAssembler.resolvePrompt.mockResolvedValueOnce(
      '[SYSTEM]\nsuper\n\n[SEGMENT]\nsegment\n\n[PLAN]\nplan\n\n[CUSTOM]\ncustom',
    );
    mocks.openaiProvider.complete.mockResolvedValueOnce({
      content: 'final',
      model: 'gpt-4o',
      finishReason: 'stop',
      promptTokens: 5,
      completionTokens: 3,
      totalTokens: 8,
    });

    await service.execute({
      ...baseRequest,
      superPrompt: 'super',
      segmentPrompt: 'segment',
      planPrompt: 'plan',
      agentSystemPrompt: 'agent-system',
      agentFilePrompts: ['file-a'],
      messages: [{ role: 'user', content: 'oi' }],
    });

    const firstCallArg = (mocks.openaiProvider.complete as jest.Mock).mock
      .calls[0][0] as {
      messages: Array<{ role: string; content: string }>;
    };

    expect(firstCallArg.messages[0].content).toBe(
      'agent-system\n\nfile-a\n\n[SYSTEM]\nsuper\n\n[SEGMENT]\nsegment\n\n[PLAN]\nplan\n\n[CUSTOM]\ncustom',
    );
  });

  it('logs warning when finish_reason is length (CA: truncated response)', async () => {
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

    const warnSpy = jest
      .spyOn(
        (service as unknown as { logger: { warn: jest.Mock } }).logger,
        'warn',
      )
      .mockImplementation(() => undefined);

    mocks.openaiProvider.complete.mockResolvedValueOnce({
      content: 'truncated output...',
      model: 'gpt-4o',
      finishReason: 'length',
      promptTokens: 10,
      completionTokens: 800,
      totalTokens: 810,
    });

    const response = await service.execute({
      ...baseRequest,
      maxTokens: 800,
    });

    expect(warnSpy).toHaveBeenCalledWith(
      expect.stringContaining('Completion truncated by max_tokens'),
    );
    expect(response['status']).toBe('completed');
    expect(mocks.aiMetrics.recordTruncatedResponse).toHaveBeenCalledWith(
      'agent-1',
    );

    warnSpy.mockRestore();
  });

  it('records blocked tool in toolCalls when guardrail denies execution', async () => {
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
          { name: 'lookup_customer', arguments: { id: 1 } },
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

    mocks.guardrail.evaluateToolCall.mockReturnValueOnce({
      allowed: false,
      reason: 'Policy violation: restricted tool',
    });

    const response = await service.execute({
      ...baseRequest,
      maxToolIterations: 5,
      runTokenBudget: 1000,
    });

    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledTimes(1);
    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledWith(
      'send_message',
      { ticket_id: 'ticket-1', content: 'final' },
      expect.objectContaining({ tenantId: 'tenant-1' }),
    );

    const output = response['output'] as Record<string, unknown>;
    const toolCalls = output['tool_calls'] as Array<Record<string, unknown>>;
    expect(toolCalls.length).toBe(2);
    expect(toolCalls[0]['blocked']).toBe(true);
    expect(toolCalls[0]['reason']).toBe('Policy violation: restricted tool');
    expect(toolCalls[1]['implicit']).toBe(true);
  });

  it('exits loop immediately when parsedToolCalls is empty (malformed content)', async () => {
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
      content: 'not-json-tool-calls',
      model: 'gpt-4o',
      finishReason: 'tool_calls',
      promptTokens: 10,
      completionTokens: 5,
      totalTokens: 15,
    });

    const response = await service.execute({
      ...baseRequest,
      maxToolIterations: 5,
      runTokenBudget: 1000,
    });

    // Loop should break immediately; finalizer may emit implicit send_message.
    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledTimes(1);
    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledWith(
      'send_message',
      expect.any(Object),
      expect.objectContaining({
        runId: 'run-1',
        tenantId: 'tenant-1',
      }),
    );
    expect(response['iterations_count']).toBe(1);
    expect(response['early_exit_reason']).toBeNull();
  });

  it('passes raw tool result when compactToolResults is false', async () => {
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

    const bigPayload = { key: 'x'.repeat(1000) };

    mocks.openaiProvider.complete
      .mockResolvedValueOnce({
        content: toolCallContent([
          { name: 'lookup_customer', arguments: { id: 1 } },
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

    mocks.toolExecutor.executeTool.mockResolvedValueOnce({
      success: true,
      data: bigPayload,
    });

    await service.execute({
      ...baseRequest,
      maxToolIterations: 2,
      runTokenBudget: 1000,
      compactToolResults: false,
    });

    const secondCallArg = (mocks.openaiProvider.complete as jest.Mock).mock
      .calls[1][0] as {
      messages: Array<{ role: string; content: string }>;
    };

    const reinjected = secondCallArg.messages.find(
      (msg) => msg.role === 'system' && msg.content.startsWith('tool_result:'),
    );

    expect(reinjected).toBeDefined();
    // With compactToolResults=false, full payload is injected without truncation
    expect(reinjected?.content).not.toContain('...[truncated]');
    expect(reinjected?.content).toContain('x'.repeat(1000));
  });

  it('includes error field in compacted result when tool returns error', async () => {
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
          { name: 'lookup_customer', arguments: { id: 1 } },
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

    mocks.toolExecutor.executeTool.mockResolvedValueOnce({
      success: false,
      error: 'Customer not found',
    });

    await service.execute({
      ...baseRequest,
      maxToolIterations: 2,
      runTokenBudget: 1000,
      compactToolResults: true,
    });

    const secondCallArg = (mocks.openaiProvider.complete as jest.Mock).mock
      .calls[1][0] as {
      messages: Array<{ role: string; content: string }>;
    };

    const reinjected = secondCallArg.messages.find(
      (msg) => msg.role === 'system' && msg.content.startsWith('tool_result:'),
    );

    expect(reinjected).toBeDefined();
    expect(reinjected?.content).toContain('"error":"Customer not found"');
    expect(reinjected?.content).toContain('"success":false');
  });

  it('parses OpenAI native tool call format (type:function with nested arguments)', async () => {
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

    // OpenAI native format with type:'function' and arguments as JSON string
    const nativeFormatContent = JSON.stringify([
      {
        type: 'function',
        function: {
          name: 'lookup_customer',
          arguments: JSON.stringify({ id: 99 }),
        },
      },
    ]);

    mocks.openaiProvider.complete
      .mockResolvedValueOnce({
        content: nativeFormatContent,
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

    const response = await service.execute({
      ...baseRequest,
      maxToolIterations: 3,
      runTokenBudget: 1000,
    });

    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledTimes(2);
    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledWith(
      'lookup_customer',
      { id: 99 },
      expect.objectContaining({ tenantId: 'tenant-1' }),
    );
    expect(response['iterations_count']).toBe(1);
  });

  it('ignores non-array JSON content when finishReason is tool_calls', async () => {
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

    // Valid JSON but not an array — parseToolCalls should return []
    mocks.openaiProvider.complete.mockResolvedValueOnce({
      content: JSON.stringify({ type: 'text', value: 'not a tool call array' }),
      model: 'gpt-4o',
      finishReason: 'tool_calls',
      promptTokens: 10,
      completionTokens: 5,
      totalTokens: 15,
    });

    const response = await service.execute({
      ...baseRequest,
      maxToolIterations: 5,
      runTokenBudget: 1000,
    });

    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledTimes(1);
    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledWith(
      'send_message',
      expect.any(Object),
      expect.objectContaining({
        runId: 'run-1',
        tenantId: 'tenant-1',
      }),
    );
    expect(response['iterations_count']).toBe(1);
    expect(response['early_exit_reason']).toBeNull();
  });

  it('handles tool call with array-valued arguments (dedup normalizes arrays)', async () => {
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

    // args containing array and multi-key object → exercises normalizeForHash array+sort paths
    mocks.openaiProvider.complete
      .mockResolvedValueOnce({
        content: toolCallContent([
          {
            name: 'lookup_customer',
            arguments: { ids: [3, 1, 2], filter: { b: 'x', a: 'y' } },
          },
          {
            name: 'lookup_customer',
            arguments: { ids: [3, 1, 2], filter: { b: 'x', a: 'y' } },
          },
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

    const response = await service.execute({
      ...baseRequest,
      maxToolIterations: 3,
      runTokenBudget: 1000,
    });

    // Second call is deduplicated even though args contain nested arrays/objects
    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledTimes(2);
    const output = response['output'] as Record<string, unknown>;
    const toolCalls = output['tool_calls'] as Array<Record<string, unknown>>;
    expect(toolCalls[1]['deduplicated']).toBe(true);
  });

  it('treats null tool arguments as empty object (defensive path)', async () => {
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

    // null arguments — toObject(null) returns {} defensively
    mocks.openaiProvider.complete.mockResolvedValueOnce({
      content: JSON.stringify([{ name: 'send_message', arguments: null }]),
      model: 'gpt-4o',
      finishReason: 'tool_calls',
      promptTokens: 10,
      completionTokens: 5,
      totalTokens: 15,
    });

    const response = await service.execute({
      ...baseRequest,
      maxToolIterations: 5,
      runTokenBudget: 1000,
    });

    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledWith(
      'send_message',
      {},
      expect.anything(),
    );
    expect(response['early_exit_reason']).toBe('send_message_completed');
  });

  it('gracefully handles non-object item in tool call array (defensive parse)', async () => {
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

    // First item is a number (non-object), second is valid — only second runs
    mocks.openaiProvider.complete.mockResolvedValueOnce({
      content: JSON.stringify([
        42,
        null,
        { name: 'send_message', arguments: { text: 'hi' } },
      ]),
      model: 'gpt-4o',
      finishReason: 'tool_calls',
      promptTokens: 10,
      completionTokens: 5,
      totalTokens: 15,
    });

    await service.execute({
      ...baseRequest,
      maxToolIterations: 5,
      runTokenBudget: 1000,
    });

    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledTimes(1);
    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledWith(
      'send_message',
      { text: 'hi' },
      expect.anything(),
    );
  });

  it('parses tool with invalid fn.arguments JSON string gracefully (uses empty object)', async () => {
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

    // fn.arguments is a string but NOT valid JSON
    mocks.openaiProvider.complete
      .mockResolvedValueOnce({
        content: JSON.stringify([
          {
            type: 'function',
            function: { name: 'lookup_customer', arguments: '{invalid-json' },
          },
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

    await service.execute({
      ...baseRequest,
      maxToolIterations: 3,
      runTokenBudget: 1000,
    });

    // Tool should still execute with empty object args
    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledWith(
      'lookup_customer',
      {},
      expect.anything(),
    );
  });

  it('recognises tools with direct name field (not nested function format)', async () => {
    const mocks = buildMocks();
    // Override internalAiClient to return tools in direct-name format
    mocks.internalAiClient.fetchToolsCached.mockResolvedValueOnce([
      {
        name: 'send_message',
        description: 'Send final message',
        parameters: { type: 'object', properties: {} },
      },
    ]);

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
      content: 'final',
      model: 'gpt-4o',
      finishReason: 'stop',
      promptTokens: 8,
      completionTokens: 4,
      totalTokens: 12,
    });

    const response = await service.execute({
      ...baseRequest,
    });

    // Run completes normally when tools use the direct-name format
    expect(response['status']).toBe('completed');
  });

  it('emits streaming chunks and final event when streamingEnabled is true', async () => {
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
      content: 'streaming response content',
      model: 'gpt-4o',
      finishReason: 'stop',
      promptTokens: 8,
      completionTokens: 4,
      totalTokens: 12,
    });

    await service.execute({
      ...baseRequest,
      streamingEnabled: true,
    });

    expect(mocks.streamHandler.emitChunk).toHaveBeenCalled();
    expect(mocks.streamHandler.emitFinal).toHaveBeenCalledWith(
      'tenant-1',
      'run-1',
      'streaming response content',
      undefined,
      'corr-1',
    );
  });

  it('treats array-valued tool arguments as empty object in tool call (toObject guard)', async () => {
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

    // arguments is an array — toObject([]) returns {} defensively (L474)
    mocks.openaiProvider.complete.mockResolvedValueOnce({
      content: JSON.stringify([{ name: 'send_message', arguments: [] }]),
      model: 'gpt-4o',
      finishReason: 'tool_calls',
      promptTokens: 10,
      completionTokens: 5,
      totalTokens: 15,
    });

    await service.execute({
      ...baseRequest,
      maxToolIterations: 5,
      runTokenBudget: 1000,
    });

    // Tool must be called with empty object (array arguments normalised to {})
    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledWith(
      'send_message',
      {},
      expect.anything(),
    );
  });

  it('skips null tool entries returned by fetchTools (toFunctionTool null guard)', async () => {
    const mocks = buildMocks();
    // fetchTools returns a null entry mixed with valid tool
    const malformedTools = [
      null, // null entry → toFunctionTool returns null (L501)
      {
        type: 'unsupported', // type ≠ 'function' and no direct name → returns null (L520)
        desc: 'not a function tool',
      },
      {
        type: 'function',
        function: { name: 'send_message', description: 'desc', parameters: {} },
      },
    ] as unknown as Awaited<ReturnType<InternalAiClientService['fetchTools']>>;

    mocks.internalAiClient.fetchToolsCached.mockResolvedValueOnce(
      malformedTools,
    );

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
      content: 'final answer',
      model: 'gpt-4o',
      finishReason: 'stop',
      promptTokens: 8,
      completionTokens: 4,
      totalTokens: 12,
    });

    const response = await service.execute({ ...baseRequest });

    // Only the valid 'function' tool should appear in the completion request
    const firstCallArg = (mocks.openaiProvider.complete as jest.Mock).mock
      .calls[0][0] as {
      tools?: Array<{ type: string; function: { name: string } }>;
    };

    expect(firstCallArg.tools).toHaveLength(1);
    expect(firstCallArg.tools?.[0].function.name).toBe('send_message');
    expect(response['status']).toBe('completed');
  });

  it('propagates provider timeout without retrying or switching model', async () => {
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

    const timeoutError = new Error('timeout of 20000ms exceeded');
    mocks.openaiProvider.complete.mockRejectedValue(timeoutError);

    await expect(
      service.execute({
        ...baseRequest,
        model: 'gpt-4o',
      }),
    ).rejects.toThrow('timeout of 20000ms exceeded');

    expect(mocks.openaiProvider.complete).toHaveBeenCalledTimes(1);
    expect(mocks.openaiProvider.complete).toHaveBeenCalledWith(
      expect.objectContaining({
        model: 'gpt-4o',
      }),
    );
  });

  // ── resolvePositiveInteger — string input branch (CA: lines 496-498) ───

  it('accepts string-typed maxToolIterations and parses it as a number', async () => {
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
          { name: 'lookup_customer', arguments: { id: 1 } },
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

    // Pass maxToolIterations as a string — resolvePositiveInteger should parse it
    const response = await service.execute({
      ...baseRequest,
      maxToolIterations: '1' as unknown as number,
      runTokenBudget: 1000,
    });

    // String '1' is parsed to integer 1 → exactly 1 iteration allowed
    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledTimes(2);
    expect(response['iterations_count']).toBe(1);
  });

  it('exits loop when delegate_to_agent succeeds (delegation_completed)', async () => {
    const mocks = buildMocks();

    mocks.internalAiClient.fetchToolsCached.mockResolvedValueOnce([
      {
        name: 'delegate_to_agent',
        description: 'Delegate to another agent',
        parameters: { type: 'object', properties: {} },
      },
    ]);

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
        { name: 'delegate_to_agent', arguments: { agent: 'billing' } },
      ]),
      model: 'gpt-4o',
      finishReason: 'tool_calls',
      promptTokens: 12,
      completionTokens: 6,
      totalTokens: 18,
    });

    mocks.toolExecutor.executeTool.mockResolvedValueOnce({
      success: true,
      delegated: true,
      target_agent: 'billing',
    });

    const response = await service.execute({
      ...baseRequest,
      maxToolIterations: 5,
      runTokenBudget: 1000,
    });

    // Loop should exit after first iteration — no second completion call
    expect(mocks.openaiProvider.complete).toHaveBeenCalledTimes(1);
    expect(mocks.toolExecutor.executeTool).toHaveBeenCalledTimes(1);
    expect(response['early_exit_reason']).toBe('delegation_completed');
    expect(response['iterations_count']).toBe(1);
    expect(mocks.aiMetrics.recordEarlyExit).toHaveBeenCalledWith(
      'agent-1',
      'delegation_completed',
    );
  });

  it('requests text-only fallback when max iterations reached with pending tool_calls', async () => {
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

    // 3 iterations of tool_calls (maxToolIterations=3), then fallback completion
    mocks.openaiProvider.complete
      .mockResolvedValueOnce({
        content: toolCallContent([
          { name: 'lookup_customer', arguments: { id: 1 } },
        ]),
        model: 'gpt-4o',
        finishReason: 'tool_calls',
        promptTokens: 10,
        completionTokens: 5,
        totalTokens: 15,
      })
      .mockResolvedValueOnce({
        content: toolCallContent([
          { name: 'lookup_customer', arguments: { id: 2 } },
        ]),
        model: 'gpt-4o',
        finishReason: 'tool_calls',
        promptTokens: 10,
        completionTokens: 5,
        totalTokens: 15,
      })
      .mockResolvedValueOnce({
        content: toolCallContent([
          { name: 'lookup_customer', arguments: { id: 3 } },
        ]),
        model: 'gpt-4o',
        finishReason: 'tool_calls',
        promptTokens: 10,
        completionTokens: 5,
        totalTokens: 15,
      })
      .mockResolvedValueOnce({
        content: toolCallContent([
          { name: 'lookup_customer', arguments: { id: 4 } },
        ]),
        model: 'gpt-4o',
        finishReason: 'tool_calls',
        promptTokens: 10,
        completionTokens: 5,
        totalTokens: 15,
      })
      // Fallback completion WITHOUT tools — should return text
      .mockResolvedValueOnce({
        content: 'Desculpe, não consegui processar sua solicitação.',
        model: 'gpt-4o',
        finishReason: 'stop',
        promptTokens: 20,
        completionTokens: 10,
        totalTokens: 30,
      });

    const response = await service.execute({
      ...baseRequest,
      maxToolIterations: 3,
      runTokenBudget: 10000,
    });

    // 3 loop iterations + 1 final loop re-call + 1 fallback = 5 complete calls
    expect(mocks.openaiProvider.complete).toHaveBeenCalledTimes(5);

    // The fallback call must NOT include tools
    const fallbackCallArg = (mocks.openaiProvider.complete as jest.Mock).mock
      .calls[4][0] as Record<string, unknown>;
    expect(fallbackCallArg['tools']).toBeUndefined();
    expect(fallbackCallArg['stream']).toBe(false);

    expect(response['early_exit_reason']).toBe('max_iterations_fallback');
    // 15*4 (loop completions) + 30 (fallback) = 90
    expect(response['total_tokens_used']).toBe(90);
    expect(mocks.aiMetrics.recordEarlyExit).toHaveBeenCalledWith(
      'agent-1',
      'max_iterations_fallback',
    );

    const output = response['output'] as Record<string, unknown>;
    expect(output['content']).toBe(
      'Desculpe, não consegui processar sua solicitação.',
    );
    expect(output['finish_reason']).toBe('stop');
  });

  it('falls back to default when maxToolIterations is a non-numeric string', async () => {
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
      content: 'final',
      model: 'gpt-4o',
      finishReason: 'stop',
      promptTokens: 5,
      completionTokens: 3,
      totalTokens: 8,
    });

    // 'abc' is not parseable → falls back to DEFAULT_MAX_TOOL_ITERATIONS (5)
    const response = await service.execute({
      ...baseRequest,
      maxToolIterations: 'abc' as unknown as number,
      runTokenBudget: 1000,
    });

    // No tool calls, run completes normally with defaults applied
    expect(response['status']).toBe('completed');
    expect(response['iterations_count']).toBe(0);
  });
});
