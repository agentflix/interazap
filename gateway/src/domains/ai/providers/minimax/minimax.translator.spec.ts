import { MiniMaxTranslator, MiniMaxChatResponse } from './minimax.translator';

/**
 * Testes do MiniMaxTranslator
 *
 * Verifica tradução de respostas OpenAI-compatible para DTO normalizado.
 */
describe('MiniMaxTranslator', () => {
  let translator: MiniMaxTranslator;

  beforeEach(() => {
    translator = new MiniMaxTranslator();
  });

  it('should translate MiniMax response into normalized completion DTO', () => {
    const response: MiniMaxChatResponse = {
      id: 'chatcmpl-123',
      object: 'chat.completion',
      created: 1700000000,
      model: 'MiniMax-M2.5',
      choices: [
        {
          index: 0,
          message: { role: 'assistant', content: 'Hello from MiniMax!' },
          finish_reason: 'stop',
        },
      ],
      usage: {
        prompt_tokens: 10,
        completion_tokens: 20,
        total_tokens: 30,
      },
    };

    const result = translator.translate(response, 'MiniMax-M2.5');

    expect(result.content).toBe('Hello from MiniMax!');
    expect(result.promptTokens).toBe(10);
    expect(result.completionTokens).toBe(20);
    expect(result.totalTokens).toBe(30);
    expect(result.model).toBe('MiniMax-M2.5');
    expect(result.finishReason).toBe('stop');
  });

  it('should handle empty choices gracefully', () => {
    const response: MiniMaxChatResponse = {
      id: 'chatcmpl-empty',
      object: 'chat.completion',
      created: 1700000000,
      model: 'MiniMax-M2.5',
      choices: [],
      usage: {
        prompt_tokens: 5,
        completion_tokens: 0,
        total_tokens: 5,
      },
    };

    const result = translator.translate(response, 'MiniMax-M2.5');

    expect(result.content).toBe('');
    expect(result.finishReason).toBeNull();
  });

  it('should default usage to zeros when missing', () => {
    const response: MiniMaxChatResponse = {
      id: 'chatcmpl-no-usage',
      object: 'chat.completion',
      created: 1700000000,
      model: 'MiniMax-M2.5',
      choices: [
        {
          index: 0,
          message: { role: 'assistant', content: 'text' },
          finish_reason: 'stop',
        },
      ],
    };

    const result = translator.translate(response, 'MiniMax-M2.5');

    expect(result.promptTokens).toBe(0);
    expect(result.completionTokens).toBe(0);
    expect(result.totalTokens).toBe(0);
  });

  it('should normalize finish reasons correctly', () => {
    const makeResponse = (finishReason: string): MiniMaxChatResponse => ({
      id: 'chatcmpl-fr',
      object: 'chat.completion',
      created: 1700000000,
      model: 'MiniMax-M2.5',
      choices: [
        {
          index: 0,
          message: { role: 'assistant', content: 'text' },
          finish_reason: finishReason,
        },
      ],
      usage: { prompt_tokens: 0, completion_tokens: 0, total_tokens: 0 },
    });

    expect(translator.translate(makeResponse('stop'), 'm').finishReason).toBe(
      'stop',
    );

    expect(translator.translate(makeResponse('length'), 'm').finishReason).toBe(
      'length',
    );

    expect(
      translator.translate(makeResponse('content_filter'), 'm').finishReason,
    ).toBe('content_filter');

    // Unknown reasons default to 'stop'
    expect(
      translator.translate(makeResponse('unknown_reason'), 'm').finishReason,
    ).toBe('stop');
  });
});
