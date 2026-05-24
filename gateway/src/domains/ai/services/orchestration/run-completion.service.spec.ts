import { Logger } from '@nestjs/common';
import { RunCompletionService } from './run-completion.service';
import { BillingUsageClient } from '../../../billing/services/billing-usage-client.service';

describe('RunCompletionService', () => {
  it('extracts user text from serialized send_message payload before implicit send', async () => {
    const guardrail = {
      evaluateFinalOutput: jest.fn().mockReturnValue({ allowed: true }),
    };
    const toolExecutor = {
      executeTool: jest.fn().mockResolvedValue({ success: true }),
    };
    const service = new RunCompletionService(
      guardrail as never,
      toolExecutor as never,
      new Logger('RunCompletionServiceSpec'),
    );

    const result = await service.finalize(
      JSON.stringify({
        name: 'send_message',
        arguments: {
          ticket_id: 'ticket-123',
          content: 'Resposta final para o cliente',
          type: 'text',
        },
      }),
      {
        tenantId: 'tenant-1',
        runId: 'run-1',
        ticketId: 'ticket-123',
      },
      [],
      'stop',
      null,
    );

    expect(guardrail.evaluateFinalOutput).toHaveBeenCalledWith(
      'Resposta final para o cliente',
    );
    expect(toolExecutor.executeTool).toHaveBeenCalledWith(
      'send_message',
      {
        ticket_id: 'ticket-123',
        content: 'Resposta final para o cliente',
      },
      expect.objectContaining({ ticketId: 'ticket-123' }),
    );
    expect(result.finalContent).toBe('Resposta final para o cliente');
    expect(result.blockedByQuota).toBe(false);
  });

  it('sends fallback when tool calls finish without customer-facing or terminal action', async () => {
    const guardrail = {
      evaluateFinalOutput: jest.fn().mockReturnValue({ allowed: true }),
    };
    const toolExecutor = {
      executeTool: jest.fn().mockResolvedValue({ success: true }),
    };
    const service = new RunCompletionService(
      guardrail as never,
      toolExecutor as never,
      new Logger('RunCompletionServiceSpec'),
    );

    const result = await service.finalize(
      '',
      {
        tenantId: 'tenant-1',
        runId: 'run-1',
        ticketId: 'ticket-123',
      },
      [
        {
          name: 'notify_seller',
          arguments: { seller_id: 'Vendas' },
          result: { success: false, data: { recoverable: true } },
        },
        {
          name: 'create_task',
          arguments: { negotiation_id: 'ticket-123' },
          result: { success: false, data: { recoverable: true } },
        },
      ],
      'tool_calls',
      null,
    );

    expect(toolExecutor.executeTool).toHaveBeenCalledWith(
      'send_message',
      {
        ticket_id: 'ticket-123',
        content:
          'Perfeito, registrei seu interesse e vou acionar nosso time comercial para continuar com você.',
      },
      expect.objectContaining({ ticketId: 'ticket-123' }),
    );
    expect(result.toolCalls.at(-1)).toMatchObject({
      name: 'send_message',
      implicit: true,
    });
  });

  it('does not send fallback after completed handoff delegation', async () => {
    const guardrail = {
      evaluateFinalOutput: jest.fn().mockReturnValue({ allowed: true }),
    };
    const toolExecutor = {
      executeTool: jest.fn().mockResolvedValue({ success: true }),
    };
    const service = new RunCompletionService(
      guardrail as never,
      toolExecutor as never,
      new Logger('RunCompletionServiceSpec'),
    );

    await service.finalize(
      '',
      {
        tenantId: 'tenant-1',
        runId: 'run-1',
        ticketId: 'ticket-123',
      },
      [
        {
          name: 'delegate_to_agent',
          arguments: { target_agent_id: 'vendas', return_after: false },
          result: { success: true, data: { return_after: false } },
        },
      ],
      'tool_calls',
      null,
    );

    expect(toolExecutor.executeTool).not.toHaveBeenCalled();
  });

  it('blocks send_message and adds handoff when quota is exceeded', async () => {
    const guardrail = {
      evaluateFinalOutput: jest.fn().mockReturnValue({ allowed: true }),
    };
    const toolExecutor = {
      executeTool: jest.fn().mockResolvedValue({ success: true }),
    };
    const billingUsageClient = {
      checkAndIncrement: jest.fn().mockResolvedValue({
        allowed: false,
        current: 100,
        limit: 100,
        mode: 'stop',
        is_overage: false,
      }),
    };
    const service = new RunCompletionService(
      guardrail as never,
      toolExecutor as never,
      new Logger('RunCompletionServiceSpec'),
      billingUsageClient as unknown as BillingUsageClient,
    );

    const result = await service.finalize(
      'Resposta que não será enviada',
      {
        tenantId: 'tenant-1',
        runId: 'run-1',
        ticketId: 'ticket-123',
        aiTurnId: 'turn-1',
      },
      [],
      'stop',
      null,
    );

    expect(billingUsageClient.checkAndIncrement).toHaveBeenCalledWith(
      'tenant-1',
      'whatsapp',
      'turn-1',
    );
    expect(toolExecutor.executeTool).toHaveBeenCalledWith(
      'transfer_to_human',
      { ticket_id: 'ticket-123', reason: 'quota_exceeded' },
      expect.objectContaining({ tenantId: 'tenant-1', ticketId: 'ticket-123' }),
    );
    expect(result.blockedByQuota).toBe(true);
    expect(result.toolCalls.at(-1)).toMatchObject({
      name: 'transfer_to_human',
      arguments: { ticket_id: 'ticket-123', reason: 'quota_exceeded' },
      blocked_by_quota: true,
    });
  });

  it('allows send_message when quota check passes', async () => {
    const guardrail = {
      evaluateFinalOutput: jest.fn().mockReturnValue({ allowed: true }),
    };
    const toolExecutor = {
      executeTool: jest.fn().mockResolvedValue({ success: true }),
    };
    const billingUsageClient = {
      checkAndIncrement: jest.fn().mockResolvedValue({
        allowed: true,
        current: 5,
        limit: 100,
        mode: 'stop',
        is_overage: false,
      }),
    };
    const service = new RunCompletionService(
      guardrail as never,
      toolExecutor as never,
      new Logger('RunCompletionServiceSpec'),
      billingUsageClient as unknown as BillingUsageClient,
    );

    const result = await service.finalize(
      'Resposta permitida',
      {
        tenantId: 'tenant-1',
        runId: 'run-1',
        ticketId: 'ticket-123',
        aiTurnId: 'turn-1',
      },
      [],
      'stop',
      null,
    );

    expect(billingUsageClient.checkAndIncrement).toHaveBeenCalled();
    expect(toolExecutor.executeTool).toHaveBeenCalledWith(
      'send_message',
      expect.any(Object),
      expect.any(Object),
    );
    expect(result.blockedByQuota).toBe(false);
  });
});
