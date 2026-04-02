/**
 * AiRunRequestConsumer — Unit tests (PRD-AI-004 phase 2)
 *
 * Validates that:
 * - processMessage correctly routes orchestrator vs provider requests
 * - New payload fields (max_tokens, max_tool_iterations, run_token_budget,
 *   compact_tool_results) are extracted and forwarded to the orchestrator
 * - Fallback to env-var defaults when fields are absent
 * - Error paths publish structured error responses and ack the message
 */

import { ConfigService } from '@nestjs/config';
import { AiRunRequestConsumer } from '../consumers/ai-completion.consumer';
import { RedisStreamsService } from '../../../infrastructure/redis/redis-streams.service';
import { AIProviderFactory } from '../providers/ai-provider.factory';
import { AiRunOrchestratorService } from '../services/ai-run-orchestrator.service';
import { OpenAIProviderError } from '../providers/openai/openai-provider.adapter';
import { UnknownProviderError } from '../providers/ai-provider.factory';
import { GatewayConfigService } from '../../../shared/services/gateway-config.service';

type StreamMessage = {
  id: string;
  message: {
    correlationId: string;
    action: string;
    provider: string;
    payload: Record<string, unknown>;
    metadata?: Record<string, unknown>;
  };
};

const buildMocks = () => {
  const redisStreams = {
    ensureConsumerGroup: jest.fn().mockResolvedValue(undefined),
    readGroup: jest.fn().mockResolvedValue([]),
    ack: jest.fn().mockResolvedValue(undefined),
    publish: jest.fn().mockResolvedValue(undefined),
    publishResponse: jest.fn().mockResolvedValue(undefined),
    createSuccessResponse: jest
      .fn()
      .mockReturnValue({ status: 'success', data: {} }),
    createErrorResponse: jest.fn().mockReturnValue({
      status: 'error',
      error: { code: 'TEST_ERROR', message: 'err' },
    }),
  } as unknown as jest.Mocked<RedisStreamsService>;

  const providerFactory = {
    getProvider: jest.fn().mockReturnValue({
      complete: jest.fn().mockResolvedValue({ content: 'ok' }),
    }),
  } as unknown as jest.Mocked<AIProviderFactory>;

  const orchestrator = {
    execute: jest.fn().mockResolvedValue({
      run_id: 'run-1',
      status: 'completed',
      output: { content: 'done' },
    }),
  } as unknown as jest.Mocked<AiRunOrchestratorService>;

  const configService = {
    get: jest.fn().mockReturnValue(undefined),
  } as unknown as jest.Mocked<ConfigService>;

  const gatewayConfigService = {
    isTestEnvironment: jest.fn().mockReturnValue(true),
  } as unknown as GatewayConfigService;

  return {
    redisStreams,
    providerFactory,
    orchestrator,
    configService,
    gatewayConfigService,
  };
};

const makeOrchestratorMessage = (
  payloadOverrides: Record<string, unknown> = {},
): StreamMessage => ({
  id: 'msg-1',
  message: {
    correlationId: 'corr-1',
    action: 'run',
    provider: 'openai',
    payload: {
      tenant_id: 'tenant-1',
      run_id: 'run-1',
      agent_id: 'agent-1',
      ...payloadOverrides,
    },
  },
});

describe('AiRunRequestConsumer.processMessage (PRD-AI-004 phase 2)', () => {
  it('does not init consumer loop in test environment', async () => {
    const {
      redisStreams,
      providerFactory,
      orchestrator,
      configService,
      gatewayConfigService,
    } = buildMocks();
    const consumer = new AiRunRequestConsumer(
      configService,
      gatewayConfigService,
      redisStreams,
      providerFactory,
      orchestrator,
    );

    await consumer.onModuleInit();
    expect(redisStreams.ensureConsumerGroup).not.toHaveBeenCalled();
  });

  it('routes to orchestrator for action=run and forwards new payload fields', async () => {
    const {
      redisStreams,
      providerFactory,
      orchestrator,
      configService,
      gatewayConfigService,
    } = buildMocks();
    const consumer = new AiRunRequestConsumer(
      configService,
      gatewayConfigService,
      redisStreams,
      providerFactory,
      orchestrator,
    );

    const msg = makeOrchestratorMessage({
      max_tokens: 600,
      max_tool_iterations: 3,
      run_token_budget: 2000,
      compact_tool_results: true,
      ticket_id: 'tkt-1',
      trace_id: 'trace-1',
    });

    await (
      consumer as unknown as {
        processMessage: (m: StreamMessage) => Promise<void>;
      }
    ).processMessage(msg);

    expect(orchestrator.execute).toHaveBeenCalledWith(
      expect.objectContaining({
        correlationId: 'corr-1',
        tenantId: 'tenant-1',
        runId: 'run-1',
        ticketId: 'tkt-1',
        traceId: 'trace-1',
        maxTokens: 600,
        maxToolIterations: 3,
        runTokenBudget: 2000,
        compactToolResults: true,
      }),
    );
    expect(redisStreams.publish).toHaveBeenCalled();
    expect(redisStreams.ack).toHaveBeenCalledWith(
      'ai.run.request',
      expect.any(String),
      'msg-1',
    );
  });

  it('uses env-var defaults when new payload fields are absent', async () => {
    const {
      redisStreams,
      providerFactory,
      orchestrator,
      configService,
      gatewayConfigService,
    } = buildMocks();
    const consumer = new AiRunRequestConsumer(
      configService,
      gatewayConfigService,
      redisStreams,
      providerFactory,
      orchestrator,
    );

    const msg = makeOrchestratorMessage();
    // No max_tokens, max_tool_iterations, run_token_budget or compact_tool_results in payload

    await (
      consumer as unknown as {
        processMessage: (m: StreamMessage) => Promise<void>;
      }
    ).processMessage(msg);

    expect(orchestrator.execute).toHaveBeenCalledWith(
      expect.objectContaining({
        maxTokens: 800,
        maxToolIterations: 5,
        runTokenBudget: 32000,
        compactToolResults: true,
      }),
    );
  });

  it('parses JSON-encoded arrays from stream payload for agent files and messages', async () => {
    const {
      redisStreams,
      providerFactory,
      orchestrator,
      configService,
      gatewayConfigService,
    } = buildMocks();
    const consumer = new AiRunRequestConsumer(
      configService,
      gatewayConfigService,
      redisStreams,
      providerFactory,
      orchestrator,
    );

    const msg = makeOrchestratorMessage({
      agent_file_prompts: JSON.stringify([
        '[IDENTITY] name rules',
        '[SOUL] tone rules',
      ]),
      delegation_stack: JSON.stringify(['root-agent', 'sales-agent']),
      messages: JSON.stringify([
        { role: 'system', content: 'prefilled' },
        { role: 'user', content: 'qual seu nome?' },
      ]),
    });

    await (
      consumer as unknown as {
        processMessage: (m: StreamMessage) => Promise<void>;
      }
    ).processMessage(msg);

    expect(orchestrator.execute).toHaveBeenCalledWith(
      expect.objectContaining({
        agentFilePrompts: ['[IDENTITY] name rules', '[SOUL] tone rules'],
        delegationStack: ['root-agent', 'sales-agent'],
        messages: [
          { role: 'system', content: 'prefilled' },
          { role: 'user', content: 'qual seu nome?' },
        ],
      }),
    );
  });

  it('forwards hydrated snapshot payload (prompt/context/tools) to orchestrator', async () => {
    const {
      redisStreams,
      providerFactory,
      orchestrator,
      configService,
      gatewayConfigService,
    } = buildMocks();
    const consumer = new AiRunRequestConsumer(
      configService,
      gatewayConfigService,
      redisStreams,
      providerFactory,
      orchestrator,
    );

    const hydratedAt = new Date().toISOString();
    const msg = makeOrchestratorMessage({
      prompt: 'snapshot prompt',
      context: JSON.stringify({ ticket: { id: 'tkt-ctx' } }),
      tools: JSON.stringify([
        { name: 'send_message', description: 'send' },
        { type: 'function', function: { name: 'lookup_customer' } },
      ]),
      hydrated_at: hydratedAt,
    });

    await (
      consumer as unknown as {
        processMessage: (m: StreamMessage) => Promise<void>;
      }
    ).processMessage(msg);

    expect(orchestrator.execute).toHaveBeenCalledWith(
      expect.objectContaining({
        promptSnapshot: { prompt: 'snapshot prompt', hydrated_at: hydratedAt },
        contextSnapshot: {
          context: { ticket: { id: 'tkt-ctx' } },
          hydrated_at: hydratedAt,
        },
        toolsSnapshot: [
          { name: 'send_message', description: 'send' },
          { type: 'function', function: { name: 'lookup_customer' } },
        ],
      }),
    );
  });

  it('publishes INVALID_PAYLOAD error and acks when tenant_id is missing', async () => {
    const {
      redisStreams,
      providerFactory,
      orchestrator,
      configService,
      gatewayConfigService,
    } = buildMocks();
    const consumer = new AiRunRequestConsumer(
      configService,
      gatewayConfigService,
      redisStreams,
      providerFactory,
      orchestrator,
    );

    const msg: StreamMessage = {
      id: 'msg-2',
      message: {
        correlationId: 'corr-2',
        action: 'run',
        provider: 'openai',
        payload: { run_id: 'run-2' }, // no tenant_id
      },
    };

    await (
      consumer as unknown as {
        processMessage: (m: StreamMessage) => Promise<void>;
      }
    ).processMessage(msg);

    expect(orchestrator.execute).not.toHaveBeenCalled();
    expect(redisStreams.createErrorResponse).toHaveBeenCalledWith(
      'corr-2',
      'INVALID_PAYLOAD',
      'tenant_id is required',
      expect.any(Number),
    );
    expect(redisStreams.publishResponse).toHaveBeenCalled();
    expect(redisStreams.ack).toHaveBeenCalledWith(
      'ai.run.request',
      expect.any(String),
      'msg-2',
    );
  });

  it('resolves tenant_id from metadata when payload field is absent', async () => {
    const {
      redisStreams,
      providerFactory,
      orchestrator,
      configService,
      gatewayConfigService,
    } = buildMocks();
    const consumer = new AiRunRequestConsumer(
      configService,
      gatewayConfigService,
      redisStreams,
      providerFactory,
      orchestrator,
    );

    const msg: StreamMessage = {
      id: 'msg-3',
      message: {
        correlationId: 'corr-3',
        action: 'run',
        provider: 'openai',
        payload: { run_id: 'run-3' }, // no tenant_id in payload…
        metadata: { tenantId: 'tenant-from-meta' }, // …but present in metadata
      },
    };

    await (
      consumer as unknown as {
        processMessage: (m: StreamMessage) => Promise<void>;
      }
    ).processMessage(msg);

    expect(orchestrator.execute).toHaveBeenCalledWith(
      expect.objectContaining({ tenantId: 'tenant-from-meta' }),
    );
  });

  it('routes to provider factory when action is not orchestrator-bound', async () => {
    const {
      redisStreams,
      providerFactory,
      orchestrator,
      configService,
      gatewayConfigService,
    } = buildMocks();
    const consumer = new AiRunRequestConsumer(
      configService,
      gatewayConfigService,
      redisStreams,
      providerFactory,
      orchestrator,
    );

    const msg: StreamMessage = {
      id: 'msg-4',
      message: {
        correlationId: 'corr-4',
        action: 'complete', // not 'run' or 'orchestrate'
        provider: 'openai',
        payload: { messages: [] }, // no run_id or tenant_id
      },
    };

    await (
      consumer as unknown as {
        processMessage: (m: StreamMessage) => Promise<void>;
      }
    ).processMessage(msg);

    expect(orchestrator.execute).not.toHaveBeenCalled();
    expect(providerFactory.getProvider).toHaveBeenCalledWith('openai');
    expect(redisStreams.createSuccessResponse).toHaveBeenCalled();
    expect(redisStreams.ack).toHaveBeenCalledWith(
      'ai.run.request',
      expect.any(String),
      'msg-4',
    );
  });

  it('publishes INTERNAL_ERROR and acks when orchestrator throws generic error', async () => {
    const {
      redisStreams,
      providerFactory,
      orchestrator,
      configService,
      gatewayConfigService,
    } = buildMocks();
    const consumer = new AiRunRequestConsumer(
      configService,
      gatewayConfigService,
      redisStreams,
      providerFactory,
      orchestrator,
    );

    orchestrator.execute.mockRejectedValueOnce(
      new Error('Unexpected runtime failure'),
    );

    const msg = makeOrchestratorMessage();

    await (
      consumer as unknown as {
        processMessage: (m: StreamMessage) => Promise<void>;
      }
    ).processMessage(msg);

    expect(redisStreams.createErrorResponse).toHaveBeenCalledWith(
      'corr-1',
      'INTERNAL_ERROR',
      'Unexpected runtime failure',
      expect.any(Number),
    );
    expect(redisStreams.publishResponse).toHaveBeenCalled();
    expect(redisStreams.ack).toHaveBeenCalledWith(
      'ai.run.request',
      expect.any(String),
      'msg-1',
    );
  });

  it('maps OpenAIProviderError to its specific error code', async () => {
    const {
      redisStreams,
      providerFactory,
      orchestrator,
      configService,
      gatewayConfigService,
    } = buildMocks();
    const consumer = new AiRunRequestConsumer(
      configService,
      gatewayConfigService,
      redisStreams,
      providerFactory,
      orchestrator,
    );

    const openaiError = new OpenAIProviderError(
      'PROVIDER_RATE_LIMIT',
      'Rate limit exceeded',
    );
    orchestrator.execute.mockRejectedValueOnce(openaiError);

    const msg = makeOrchestratorMessage();

    await (
      consumer as unknown as {
        processMessage: (m: StreamMessage) => Promise<void>;
      }
    ).processMessage(msg);

    expect(redisStreams.createErrorResponse).toHaveBeenCalledWith(
      'corr-1',
      'PROVIDER_RATE_LIMIT',
      'Rate limit exceeded',
      expect.any(Number),
    );
  });

  it('maps UnknownProviderError to UNKNOWN_PROVIDER code', async () => {
    const {
      redisStreams,
      providerFactory,
      orchestrator,
      configService,
      gatewayConfigService,
    } = buildMocks();
    const consumer = new AiRunRequestConsumer(
      configService,
      gatewayConfigService,
      redisStreams,
      providerFactory,
      orchestrator,
    );

    const unknownError = new UnknownProviderError('bad-provider');
    orchestrator.execute.mockRejectedValueOnce(unknownError);

    const msg = makeOrchestratorMessage();

    await (
      consumer as unknown as {
        processMessage: (m: StreamMessage) => Promise<void>;
      }
    ).processMessage(msg);

    expect(redisStreams.createErrorResponse).toHaveBeenCalledWith(
      'corr-1',
      'UNKNOWN_PROVIDER',
      expect.stringContaining('bad-provider'),
      expect.any(Number),
    );
  });

  it('routes to orchestrator when action is orchestrate', async () => {
    const {
      redisStreams,
      providerFactory,
      orchestrator,
      configService,
      gatewayConfigService,
    } = buildMocks();
    const consumer = new AiRunRequestConsumer(
      configService,
      gatewayConfigService,
      redisStreams,
      providerFactory,
      orchestrator,
    );

    const msg: StreamMessage = {
      id: 'msg-5',
      message: {
        correlationId: 'corr-5',
        action: 'orchestrate',
        provider: 'openai',
        payload: { tenant_id: 'tenant-5', run_id: 'run-5' },
      },
    };

    await (
      consumer as unknown as {
        processMessage: (m: StreamMessage) => Promise<void>;
      }
    ).processMessage(msg);

    expect(orchestrator.execute).toHaveBeenCalledWith(
      expect.objectContaining({
        correlationId: 'corr-5',
        tenantId: 'tenant-5',
      }),
    );
  });

  it('sets isShuttingDown on destroy', () => {
    const {
      redisStreams,
      providerFactory,
      orchestrator,
      configService,
      gatewayConfigService,
    } = buildMocks();
    const consumer = new AiRunRequestConsumer(
      configService,
      gatewayConfigService,
      redisStreams,
      providerFactory,
      orchestrator,
    );

    consumer.onModuleDestroy();

    expect(
      (consumer as unknown as { isShuttingDown: boolean }).isShuttingDown,
    ).toBe(true);
    expect((consumer as unknown as { isRunning: boolean }).isRunning).toBe(
      false,
    );
  });

  it('accepts number fields as strings in payload (backward compat)', async () => {
    const {
      redisStreams,
      providerFactory,
      orchestrator,
      configService,
      gatewayConfigService,
    } = buildMocks();
    const consumer = new AiRunRequestConsumer(
      configService,
      gatewayConfigService,
      redisStreams,
      providerFactory,
      orchestrator,
    );

    const msg = makeOrchestratorMessage({
      max_tokens: '512',
      max_tool_iterations: '4',
      run_token_budget: '1500',
      compact_tool_results: 'false',
    });

    await (
      consumer as unknown as {
        processMessage: (m: StreamMessage) => Promise<void>;
      }
    ).processMessage(msg);

    expect(orchestrator.execute).toHaveBeenCalledWith(
      expect.objectContaining({
        maxTokens: 512,
        maxToolIterations: 4,
        runTokenBudget: 1500,
        compactToolResults: false,
      }),
    );
  });

  it('forwards delegation_stack array and messages array from payload', async () => {
    const {
      redisStreams,
      providerFactory,
      orchestrator,
      configService,
      gatewayConfigService,
    } = buildMocks();
    const consumer = new AiRunRequestConsumer(
      configService,
      gatewayConfigService,
      redisStreams,
      providerFactory,
      orchestrator,
    );

    const msg = makeOrchestratorMessage({
      delegation_stack: ['agent-parent', 'agent-child'],
      delegation_depth: 1,
      messages: [
        { role: 'system', content: 'You are an assistant.' },
        { role: 'user', content: 'Hello' },
      ],
    });

    await (
      consumer as unknown as {
        processMessage: (m: StreamMessage) => Promise<void>;
      }
    ).processMessage(msg);

    expect(orchestrator.execute).toHaveBeenCalledWith(
      expect.objectContaining({
        delegationStack: ['agent-parent', 'agent-child'],
        delegationDepth: 1,
        messages: [
          { role: 'system', content: 'You are an assistant.' },
          { role: 'user', content: 'Hello' },
        ],
      }),
    );
  });

  it('skips messages with invalid role in payload', async () => {
    const {
      redisStreams,
      providerFactory,
      orchestrator,
      configService,
      gatewayConfigService,
    } = buildMocks();
    const consumer = new AiRunRequestConsumer(
      configService,
      gatewayConfigService,
      redisStreams,
      providerFactory,
      orchestrator,
    );

    const msg = makeOrchestratorMessage({
      messages: [
        { role: 'user', content: 'valid' },
        { role: 'invalid_role', content: 'ignored' },
        null, // null entry
        42, // non-object entry
      ],
    });

    await (
      consumer as unknown as {
        processMessage: (m: StreamMessage) => Promise<void>;
      }
    ).processMessage(msg);

    expect(orchestrator.execute).toHaveBeenCalledWith(
      expect.objectContaining({
        messages: [{ role: 'user', content: 'valid' }],
      }),
    );
  });

  it('initialises consume loop outside test environment', async () => {
    const {
      redisStreams,
      providerFactory,
      orchestrator,
      configService,
      gatewayConfigService,
    } = buildMocks();
    (gatewayConfigService.isTestEnvironment as jest.Mock).mockReturnValue(
      false,
    );
    const consumer = new AiRunRequestConsumer(
      configService,
      gatewayConfigService,
      redisStreams,
      providerFactory,
      orchestrator,
    );

    // Prevent the infinite loop from actually running
    jest
      .spyOn(
        consumer as unknown as { consumeLoop: () => Promise<void> },
        'consumeLoop',
      )
      .mockResolvedValue(undefined);

    const origNodeEnv = process.env.NODE_ENV;
    const origWorker = process.env.JEST_WORKER_ID;
    delete process.env.NODE_ENV;
    delete process.env.JEST_WORKER_ID;

    try {
      await consumer.onModuleInit();
      expect(redisStreams.ensureConsumerGroup).toHaveBeenCalledWith(
        'ai.run.request',
        expect.any(String),
      );
    } finally {
      process.env.NODE_ENV = origNodeEnv;
      if (origWorker !== undefined) {
        process.env.JEST_WORKER_ID = origWorker;
      }
    }
  });

  it('converts string tool names in tools field to minimal tool definitions', async () => {
    const {
      redisStreams,
      providerFactory,
      orchestrator,
      configService,
      gatewayConfigService,
    } = buildMocks();
    const consumer = new AiRunRequestConsumer(
      configService,
      gatewayConfigService,
      redisStreams,
      providerFactory,
      orchestrator,
    );

    const hydratedAt = '2025-01-01T00:00:00Z';
    const msg = makeOrchestratorMessage({
      tools: JSON.stringify([
        'send_message',
        'lookup_customer',
        'delegate_to_agent',
      ]),
      hydrated_at: hydratedAt,
    });

    await (
      consumer as unknown as {
        processMessage: (m: StreamMessage) => Promise<void>;
      }
    ).processMessage(msg);

    expect(orchestrator.execute).toHaveBeenCalledWith(
      expect.objectContaining({
        toolsSnapshot: [
          { name: 'send_message', description: '', parameters: {} },
          { name: 'lookup_customer', description: '', parameters: {} },
          { name: 'delegate_to_agent', description: '', parameters: {} },
        ],
      }),
    );
  });

  it('accepts tool_names_snapshot field as array of string names (backward compat)', async () => {
    const {
      redisStreams,
      providerFactory,
      orchestrator,
      configService,
      gatewayConfigService,
    } = buildMocks();
    const consumer = new AiRunRequestConsumer(
      configService,
      gatewayConfigService,
      redisStreams,
      providerFactory,
      orchestrator,
    );

    const msg = makeOrchestratorMessage({
      tool_names_snapshot: JSON.stringify(['send_message', 'lookup_customer']),
    });

    await (
      consumer as unknown as {
        processMessage: (m: StreamMessage) => Promise<void>;
      }
    ).processMessage(msg);

    expect(orchestrator.execute).toHaveBeenCalledWith(
      expect.objectContaining({
        toolsSnapshot: [
          { name: 'send_message', description: '', parameters: {} },
          { name: 'lookup_customer', description: '', parameters: {} },
        ],
      }),
    );
  });

  it('handles mixed tools array with both objects and string names', async () => {
    const {
      redisStreams,
      providerFactory,
      orchestrator,
      configService,
      gatewayConfigService,
    } = buildMocks();
    const consumer = new AiRunRequestConsumer(
      configService,
      gatewayConfigService,
      redisStreams,
      providerFactory,
      orchestrator,
    );

    const msg = makeOrchestratorMessage({
      tools: JSON.stringify([
        {
          name: 'send_message',
          description: 'Send a message',
          parameters: { type: 'object' },
        },
        'lookup_customer',
        { type: 'function', function: { name: 'delegate_to_agent' } },
      ]),
    });

    await (
      consumer as unknown as {
        processMessage: (m: StreamMessage) => Promise<void>;
      }
    ).processMessage(msg);

    expect(orchestrator.execute).toHaveBeenCalledWith(
      expect.objectContaining({
        toolsSnapshot: [
          {
            name: 'send_message',
            description: 'Send a message',
            parameters: { type: 'object' },
          },
          { name: 'lookup_customer', description: '', parameters: {} },
          { type: 'function', function: { name: 'delegate_to_agent' } },
        ],
      }),
    );
  });
});
