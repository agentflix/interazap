import { ToolExecutorService } from '../services/tool-executor.service';
import { AiMetricsService } from '../services/ai-metrics.service';
import { InternalAiClientService } from '../services/internal-ai-client.service';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { ConfigService } from '@nestjs/config';
import { ToolStrategyRegistry } from '../services/tool-strategies/tool-strategy.registry';
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
        if (key === 'ai.delegationWaitTimeoutMs') {
          return 8000;
        }

        if (key === 'ai.toolRpcTimeoutMs') {
          return 2000;
        }

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

describe('ToolExecutorService', () => {
  let service: ToolExecutorService;
  let aiMetrics: ReturnType<typeof buildMocks>['aiMetrics'];
  let internalAiClient: ReturnType<typeof buildMocks>['internalAiClient'];
  let redisService: ReturnType<typeof buildMocks>['redisService'];
  let redisClient: ReturnType<typeof buildMocks>['redisClient'];
  let gatewayConfigService: ReturnType<
    typeof buildMocks
  >['gatewayConfigService'];

  beforeEach(() => {
    const mocks = buildMocks();
    aiMetrics = mocks.aiMetrics;
    internalAiClient = mocks.internalAiClient;
    redisService = mocks.redisService;
    redisClient = mocks.redisClient;
    gatewayConfigService = mocks.gatewayConfigService;

    service = new ToolExecutorService(
      aiMetrics,
      internalAiClient,
      redisService,
      mocks.configService,
      gatewayConfigService,
      ToolStrategyRegistry.createDefault(),
    );
  });

  const context = {
    tenantId: 'tenant-1',
    runId: 'run-1',
    agentId: 'agent-1',
    traceId: 'trace-1',
    agentRole: 'support',
    ticketId: 'ticket-1',
  };

  it('should execute send_message via Redis RPC without HTTP fallback when Redis responds', async () => {
    (redisService.publishStream as jest.Mock).mockResolvedValue('1710000000-0');
    redisClient.blpop.mockResolvedValue([
      'ai:tool:reply:run-1:req-1',
      JSON.stringify({
        success: true,
        provider_message_id: 'provider-123',
      }),
    ]);

    const result = await service.executeTool(
      'send_message',
      {
        content: 'Hello from AI',
        channel: 'whatsapp',
      },
      context,
    );

    expect(redisService.publishStream).toHaveBeenCalledWith(
      'ai.tool.request',
      expect.objectContaining({
        tool_name: 'send_message',
        reply_key: expect.stringMatching(/^ai:tool:reply:tenant-1:run-1:/),
        parameters: {
          content: 'Hello from AI',
          channel: 'whatsapp',
          ticket_id: 'ticket-1',
        },
        context: expect.objectContaining({
          tenant_id: 'tenant-1',
          current_run_id: 'run-1',
          agent_id: 'agent-1',
          ticket_id: 'ticket-1',
          trace_id: 'trace-1',
        }),
      }),
    );
    expect(redisClient.blpop).toHaveBeenCalledWith(
      expect.stringMatching(/^ai:tool:reply:tenant-1:run-1:/),
      2,
    );
    expect(internalAiClient.executeRemoteTool).not.toHaveBeenCalled();
    expect(result).toEqual({
      success: true,
      data: {
        success: true,
        provider_message_id: 'provider-123',
      },
    });
  });

  it('should map send_message text to content when content is missing', async () => {
    (redisService.publishStream as jest.Mock).mockResolvedValue('1710000000-0');
    redisClient.blpop.mockResolvedValue([
      'ai:tool:reply:run-1:req-2',
      JSON.stringify({
        success: true,
        provider_message_id: 'provider-456',
      }),
    ]);

    await service.executeTool(
      'send_message',
      {
        text: 'Hello from text alias',
        channel: 'whatsapp',
      },
      context,
    );

    expect(redisService.publishStream).toHaveBeenCalledWith(
      'ai.tool.request',
      expect.objectContaining({
        parameters: {
          text: 'Hello from text alias',
          content: 'Hello from text alias',
          channel: 'whatsapp',
          ticket_id: 'ticket-1',
        },
      }),
    );
  });

  it('should fall back to HTTP for generic tools when Redis RPC times out', async () => {
    (redisService.publishStream as jest.Mock).mockResolvedValue('1710000000-0');
    redisClient.blpop.mockResolvedValue(null);
    (internalAiClient.executeRemoteTool as jest.Mock).mockResolvedValue({
      success: true,
      data: { customer_id: 'customer-1' },
    });

    const result = await service.executeTool(
      'lookup_customer',
      { customer_id: 'customer-1' },
      context,
    );

    expect(internalAiClient.executeRemoteTool).toHaveBeenCalledWith(
      'lookup_customer',
      {
        customer_id: 'customer-1',
      },
      expect.objectContaining({
        tenant_id: 'tenant-1',
        current_run_id: 'run-1',
        agent_id: 'agent-1',
      }),
      'trace-1',
    );
    expect(result).toEqual({
      success: true,
      data: { customer_id: 'customer-1' },
    });
  });

  it('should delegate via /runs/delegate and wait on BLPOP without polling', async () => {
    (internalAiClient.delegateRun as jest.Mock).mockResolvedValue({
      child_run_id: 'child-run-1',
      status: 'queued',
    });
    redisClient.blpop.mockResolvedValue([
      'ai:delegation:result:run-1',
      JSON.stringify({
        child_run_id: 'child-run-1',
        status: 'completed',
        output: { content: 'delegated output' },
      }),
    ]);

    const result = await service.executeTool(
      'delegate_to_agent',
      {
        target_agent_id: 'agent-2',
        input_context: {
          body: 'Need billing assistance',
        },
      },
      context,
    );

    expect(internalAiClient.delegateRun).toHaveBeenCalledWith(
      {
        tenant_id: 'tenant-1',
        parent_run_id: 'run-1',
        target_agent_id: 'agent-2',
        delegation_stack: ['agent-1'],
        input_context: {
          body: 'Need billing assistance',
          ticket_id: 'ticket-1',
          trace_id: 'trace-1',
        },
      },
      'trace-1',
    );
    expect(redisClient.blpop).toHaveBeenCalledWith(
      'ai:delegation:result:run-1',
      8,
    );
    expect(internalAiClient.fetchRunStatus).not.toHaveBeenCalled();
    expect(redisService.publish).toHaveBeenCalledTimes(2);
    expect(result).toEqual({
      success: true,
      delegated: true,
      return_after: true,
      child_run_id: 'child-run-1',
      child_status: 'completed',
      child_output: { content: 'delegated output' },
      target_agent_id: 'agent-2',
      message: 'Delegation completed and child run result returned to parent.',
    });
  });

  it('should return explicit delegation timeout error when BLPOP expires', async () => {
    (internalAiClient.delegateRun as jest.Mock).mockResolvedValue({
      child_run_id: 'child-run-1',
      status: 'queued',
    });
    redisClient.blpop.mockResolvedValue(null);

    const result = await service.executeTool(
      'delegate_to_agent',
      { target_agent_id: 'agent-2' },
      context,
    );

    expect(aiMetrics.recordToolCall).toHaveBeenCalledWith(
      'agent-1',
      'delegate_to_agent',
      'error',
    );
    expect(result).toEqual({
      success: false,
      error:
        'delegation_timeout key=ai:delegation:result:run-1 timeout_ms=8000',
    });
  });

  it('should use the dedicated blocking client for BLPOP, not the shared command client', async () => {
    (redisService.publishStream as jest.Mock).mockResolvedValue('1710000000-0');
    redisClient.blpop.mockResolvedValue([
      'ai:tool:reply:run-1:req-blk',
      JSON.stringify({ success: true, data: 'ok' }),
    ]);

    await service.executeTool('send_message', { content: 'test' }, context);

    // getBlockingClient must have been called for BLPOP
    expect(redisService.getBlockingClient).toHaveBeenCalled();
  });
});
