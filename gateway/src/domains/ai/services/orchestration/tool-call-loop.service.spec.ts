import { ToolCallLoopService } from './tool-call-loop.service';

describe('ToolCallLoopService.parseToolCalls', () => {
  it('parses a single-object send_message payload', () => {
    const service = new ToolCallLoopService(null as never, null as never, 500);
    const payload = JSON.stringify({
      name: 'send_message',
      arguments: {
        ticket_id: 'ticket-1',
        content: 'Ola!',
        type: 'text',
      },
    });

    expect(service.parseToolCalls(payload)).toEqual([
      {
        name: 'send_message',
        arguments: {
          ticket_id: 'ticket-1',
          content: 'Ola!',
          type: 'text',
        },
      },
    ]);
  });

  it('parses JSON-string arguments in single-object payload', () => {
    const service = new ToolCallLoopService(null as never, null as never, 500);
    const payload = JSON.stringify({
      name: 'send_message',
      arguments: JSON.stringify({
        ticket_id: 'ticket-2',
        content: 'Tudo bem?',
      }),
    });

    expect(service.parseToolCalls(payload)).toEqual([
      {
        name: 'send_message',
        arguments: {
          ticket_id: 'ticket-2',
          content: 'Tudo bem?',
        },
      },
    ]);
  });
});
