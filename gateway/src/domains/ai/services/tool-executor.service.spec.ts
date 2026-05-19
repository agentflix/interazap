import { ToolExecutorService } from './tool-executor.service';
import { AiMetricsService } from './ai-metrics.service';
import { InternalAiClientService } from './internal-ai-client.service';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { ConfigService } from '@nestjs/config';
import { ToolStrategyRegistry } from './tool-strategies/tool-strategy.registry';
import { GatewayConfigService } from '../../../shared/services/gateway-config.service';

const buildMocks = () => {
  const redisClient = {
    blpop: jest.fn(),
    expire: jest.fn(),
  };

  return {
    aiMetrics: {
      recordToolCall: jest.fn(),
      recordDelegation: jest.fn(),
    } as unknown as jest.Mocked<AiMetricsService>,
    internalAiClient: {
      executeRemoteTool: jest.fn(),
      delegateRun: jest.fn(),
      fetchRunStatus: jest.fn(),
    } as unknown as jest.Mocked<InternalAiClientService>,
    redisService: {
      publish: jest.fn(),
      publishStream: jest.fn(),
      getClient: jest.fn().mockReturnValue(redisClient),
      getBlockingClient: jest.fn().mockReturnValue(redisClient),
    } as unknown as jest.Mocked<RedisService>,
    configService: {
      get: jest.fn((key: string) => {
        if (key === 'ai.delegationWaitTimeoutMs') return 8000;
        if (key === 'ai.toolRpcTimeoutMs') return 2000;
        return undefined;
      }),
    } as unknown as jest.Mocked<ConfigService>,
    gatewayConfigService: {
      isTestEnvironment: jest.fn().mockReturnValue(true),
      aiToolRequestStream: 'ai.tool.request',
      wsEventsChannel: 'ws.events',
    } as unknown as GatewayConfigService,
    redisClient,
  };
};

const makeService = (mocks: ReturnType<typeof buildMocks>) =>
  new ToolExecutorService(
    mocks.aiMetrics,
    mocks.internalAiClient,
    mocks.redisService,
    mocks.configService,
    mocks.gatewayConfigService,
    ToolStrategyRegistry.createDefault(),
  );

const defaultContext = {
  tenantId: 'tenant-1',
  runId: 'run-1',
  agentId: 'agent-123',
  traceId: 'trace-abc',
  agentRole: 'support',
  ticketId: 'ticket-1',
};

describe('ToolExecutorService — agent_id contract', () => {
  let mocks: ReturnType<typeof buildMocks>;
  let service: ToolExecutorService;

  beforeEach(() => {
    mocks = buildMocks();
    service = makeService(mocks);
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  it('deve enviar agent_id no contexto publicado ao Redis Stream (RPC)', async () => {
    (mocks.redisService.publishStream as jest.Mock).mockResolvedValue(
      '1710000000-0',
    );
    mocks.redisClient.blpop.mockResolvedValue([
      'ai:tool:reply:tenant-1:run-1:uuid',
      JSON.stringify({ success: true }),
    ]);

    await service.executeTool(
      'lookup_customer',
      { customer_id: 'c-1' },
      defaultContext,
    );

    const publishedPayload = (mocks.redisService.publishStream as jest.Mock)
      .mock.calls[0][1];
    expect(publishedPayload.context).toMatchObject({
      agent_id: 'agent-123',
      tenant_id: 'tenant-1',
      run_id: 'run-1',
      current_run_id: 'run-1',
      trace_id: 'trace-abc',
      ticket_id: 'ticket-1',
    });
  });

  it('deve enviar agent_id no contexto via HTTP fallback quando RPC falha', async () => {
    (mocks.redisService.publishStream as jest.Mock).mockResolvedValue(
      '1710000000-0',
    );
    mocks.redisClient.blpop.mockResolvedValue(null); // RPC timeout → fallback HTTP
    (mocks.internalAiClient.executeRemoteTool as jest.Mock).mockResolvedValue({
      success: true,
    });

    await service.executeTool(
      'lookup_customer',
      { customer_id: 'c-1' },
      defaultContext,
    );

    // Advance timers to trigger the RPC timeout
    jest.advanceTimersByTime(2100);
    await Promise.resolve();

    const httpContext = (mocks.internalAiClient.executeRemoteTool as jest.Mock)
      .mock.calls[0][2];
    expect(httpContext).toMatchObject({
      agent_id: 'agent-123',
      tenant_id: 'tenant-1',
      current_run_id: 'run-1',
    });
  });

  it('deve manter agent_role apenas para observabilidade, com fallback para "general"', async () => {
    (mocks.redisService.publishStream as jest.Mock).mockResolvedValue(
      '1710000000-0',
    );
    mocks.redisClient.blpop.mockResolvedValue([
      'ai:tool:reply:tenant-1:run-1:uuid',
      JSON.stringify({ success: true }),
    ]);

    const ctxWithoutRole = { ...defaultContext };
    delete (ctxWithoutRole as Record<string, unknown>)['agentRole'];

    await service.executeTool(
      'send_message',
      { content: 'hi' },
      ctxWithoutRole,
    );

    const publishedPayload = (mocks.redisService.publishStream as jest.Mock)
      .mock.calls[0][1];
    expect(publishedPayload.context.agent_role).toBe('general');
  });

  it('deve repassar agent_role quando presente no contexto', async () => {
    (mocks.redisService.publishStream as jest.Mock).mockResolvedValue(
      '1710000000-0',
    );
    mocks.redisClient.blpop.mockResolvedValue([
      'ai:tool:reply:tenant-1:run-1:uuid',
      JSON.stringify({ success: true }),
    ]);

    await service.executeTool(
      'send_message',
      { content: 'hi' },
      { ...defaultContext, agentRole: 'billing_specialist' },
    );

    const publishedPayload = (mocks.redisService.publishStream as jest.Mock)
      .mock.calls[0][1];
    expect(publishedPayload.context.agent_role).toBe('billing_specialist');
  });
});

describe('ToolExecutorService — tool sem permissão (bloqueio pela API)', () => {
  let mocks: ReturnType<typeof buildMocks>;
  let service: ToolExecutorService;

  beforeEach(() => {
    mocks = buildMocks();
    service = makeService(mocks);
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  it('deve retornar falha controlada quando a API rejeita tool sem vínculo em ai_agent_tools', async () => {
    // Simula cenário: RPC falha, HTTP fallback retorna erro da API
    // indicando que a tool não está vinculada ao agente
    (mocks.redisService.publishStream as jest.Mock).mockResolvedValue(
      '1710000000-0',
    );
    mocks.redisClient.blpop.mockResolvedValue(null); // RPC timeout
    (mocks.internalAiClient.executeRemoteTool as jest.Mock).mockResolvedValue({
      success: false,
      error:
        'Tool "unauthorized_tool" is not linked to agent "agent-123" in ai_agent_tools.',
      code: 'TOOL_NOT_PERMITTED',
    });

    const result = await service.executeTool(
      'unauthorized_tool',
      { some_arg: 'value' },
      defaultContext,
    );

    // O serviço repassa o resultado da API sem modificar
    expect(result.success).toBe(false);
    expect(result.error).toContain('not linked to agent');
    expect(result.code).toBe('TOOL_NOT_PERMITTED');

    // Métrica registrada como erro
    expect(mocks.aiMetrics.recordToolCall).toHaveBeenCalledWith(
      'agent-123',
      'unauthorized_tool',
      'error',
    );
  });

  it('deve retornar falha controlada quando HTTP fallback retorna 403 da API', async () => {
    (mocks.redisService.publishStream as jest.Mock).mockResolvedValue(
      '1710000000-0',
    );
    mocks.redisClient.blpop.mockResolvedValue(null);
    (mocks.internalAiClient.executeRemoteTool as jest.Mock).mockResolvedValue({
      success: false,
      error: 'Forbidden: agent does not have permission to execute this tool.',
    });

    const result = await service.executeTool(
      'restricted_tool',
      {},
      defaultContext,
    );

    expect(result.success).toBe(false);
    expect(result.error).toContain('Forbidden');
    expect(mocks.aiMetrics.recordToolCall).toHaveBeenCalledWith(
      'agent-123',
      'restricted_tool',
      'error',
    );
  });
});
