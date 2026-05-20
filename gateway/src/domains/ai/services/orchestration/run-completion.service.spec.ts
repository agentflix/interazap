import { Logger } from '@nestjs/common';
import { RunCompletionService } from './run-completion.service';

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
});
