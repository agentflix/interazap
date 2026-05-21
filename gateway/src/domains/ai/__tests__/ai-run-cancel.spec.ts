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

describe('ai.run.cancel_requested handling', () => {
  it('aborts tool loop when run is marked cancelled', async () => {
    const cancelledRuns = new Set<string>();
    const registry = {
      markCancelled: jest.fn(async (runId: string) => {
        cancelledRuns.add(runId);
      }),
      isCancelled: jest.fn(async (runId: string) => cancelledRuns.has(runId)),
      clear: jest.fn(async (runId: string) => {
        cancelledRuns.delete(runId);
      }),
    } as unknown as jest.Mocked<AiCancellationRegistry>;

    const openaiProvider = {
      complete: jest.fn().mockResolvedValue({
        content: JSON.stringify([
          { name: 'lookup_customer', arguments: { id: 1 } },
        ]),
        model: 'gpt-4o',
        finishReason: 'tool_calls',
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
      executeTool: jest.fn().mockImplementation(async () => {
        await registry.markCancelled('run-1');
        return { success: true };
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
      registry,
    );

    const response = await orchestrator.execute({
      correlationId: 'corr-1',
      tenantId: 'tenant-1',
      runId: 'run-1',
      agentId: 'agent-1',
      inputText: 'hello',
      maxToolIterations: 5,
    });

    expect(response['status']).toBe('cancelled');
    expect(response['early_exit_reason']).toBe('cancelled');
    expect(openaiProvider.complete).toHaveBeenCalledTimes(1);
  });
});
