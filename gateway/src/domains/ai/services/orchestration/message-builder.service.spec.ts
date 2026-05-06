import { MessageBuilderService } from './message-builder.service';

describe('MessageBuilderService', () => {
  it('expands conversation_history into structured messages and removes it from context JSON', () => {
    const service = new MessageBuilderService();

    const messages = service.buildMessages(
      'system prompt',
      {
        ticket_id: 'ticket-1',
        conversation_history: ['User: Oi', 'Agent: Ola, tudo bem?'],
      },
      {
        inputText: 'Quero saber os valores',
      },
      [{ name: 'send_message' }],
    );

    expect(messages).toEqual([
      { role: 'system', content: 'system prompt' },
      { role: 'system', content: 'context:{"ticket_id":"ticket-1"}' },
      {
        role: 'system',
        content:
          'You MUST use the send_message tool to respond to the user. NEVER respond with text directly - always use send_message to deliver your response to the customer.\n\nAvailable tools: send_message',
      },
      { role: 'user', content: 'Oi' },
      { role: 'assistant', content: 'Ola, tudo bem?' },
      { role: 'user', content: 'Quero saber os valores' },
    ]);
  });

  it('skips inputText when it duplicates the last user entry from conversation_history', () => {
    const service = new MessageBuilderService();

    const messages = service.buildMessages(
      'system prompt',
      {
        conversation_history: ['User: Quero saber os valores'],
      },
      {
        inputText: 'Quero saber os valores',
      },
      [],
    );

    expect(messages).toEqual([
      { role: 'system', content: 'system prompt' },
      { role: 'system', content: 'context:{}' },
      {
        role: 'system',
        content:
          'Use the available tools as needed to fulfill the user request. When done, respond with a clear text message for the user.\n\nAvailable tools: ',
      },
      { role: 'user', content: 'Quero saber os valores' },
    ]);
  });

  it('ignores malformed history lines and deduplicates the last user message by inputText', () => {
    const service = new MessageBuilderService();

    const messages = service.buildMessages(
      'system prompt',
      {
        conversation_history: [
          'User: Quero saber os valores',
          'malformed line',
          'Agent: Podemos te ajudar',
          'User: Quero contratar',
        ],
      },
      {
        inputText: 'Quero contratar',
      },
      [],
    );

    expect(messages).toEqual([
      { role: 'system', content: 'system prompt' },
      { role: 'system', content: 'context:{}' },
      {
        role: 'system',
        content:
          'Use the available tools as needed to fulfill the user request. When done, respond with a clear text message for the user.\n\nAvailable tools: ',
      },
      { role: 'user', content: 'Quero saber os valores' },
      { role: 'assistant', content: 'Podemos te ajudar' },
      { role: 'user', content: 'Quero contratar' },
    ]);
  });
});
