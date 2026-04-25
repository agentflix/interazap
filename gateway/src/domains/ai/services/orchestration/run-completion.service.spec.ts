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
});
