/**
 * OpenAI Translator Tests
 *
 * Testes unitários para o translator (ACL).
 */

import { OpenAITranslator } from '../providers/openai/openai.translator';
import type { ChatCompletion } from 'openai/resources/chat/completions';

describe('OpenAITranslator', () => {
  let translator: OpenAITranslator;

  beforeEach(() => {
    translator = new OpenAITranslator();
  });

  describe('translate', () => {
    it('should map all fields correctly from a complete response', () => {
      const response: ChatCompletion = {
        id: 'chatcmpl-123',
        object: 'chat.completion',
        created: 1677652288,
        model: 'gpt-4o-2024-08-06',
        choices: [
          {
            index: 0,
            message: {
              role: 'assistant',
              content: 'Hello, how can I help you today?',
              refusal: null,
            },
            finish_reason: 'stop',
            logprobs: null,
          },
        ],
        usage: {
          prompt_tokens: 10,
          completion_tokens: 8,
          total_tokens: 18,
        },
      };

      const result = translator.translate(response);

      expect(result.content).toBe('Hello, how can I help you today?');
      expect(result.promptTokens).toBe(10);
      expect(result.completionTokens).toBe(8);
      expect(result.totalTokens).toBe(18);
      expect(result.model).toBe('gpt-4o-2024-08-06');
      expect(result.finishReason).toBe('stop');
    });

    it('should normalize finish_reason "stop"', () => {
      const response = createMockResponse({ finish_reason: 'stop' });
      const result = translator.translate(response);
      expect(result.finishReason).toBe('stop');
    });

    it('should normalize finish_reason "length"', () => {
      const response = createMockResponse({ finish_reason: 'length' });
      const result = translator.translate(response);
      expect(result.finishReason).toBe('length');
    });

    it('should normalize finish_reason "content_filter"', () => {
      const response = createMockResponse({ finish_reason: 'content_filter' });
      const result = translator.translate(response);
      expect(result.finishReason).toBe('content_filter');
    });

    it('should normalize finish_reason "tool_calls"', () => {
      const response = createMockResponse({ finish_reason: 'tool_calls' });
      const result = translator.translate(response);
      expect(result.finishReason).toBe('tool_calls');
    });

    it('should map "function_call" to "tool_calls"', () => {
      const response = createMockResponse({
        finish_reason: 'function_call' as any,
      });
      const result = translator.translate(response);
      expect(result.finishReason).toBe('tool_calls');
    });

    it('should return null for unknown finish_reason', () => {
      const response = createMockResponse({
        finish_reason: 'unknown_reason' as any,
      });
      const result = translator.translate(response);
      expect(result.finishReason).toBeNull();
    });

    it('should return null for null finish_reason', () => {
      const response = {
        id: 'chatcmpl-123',
        object: 'chat.completion',
        created: 1677652288,
        model: 'gpt-4o',
        choices: [
          {
            index: 0,
            message: {
              role: 'assistant',
              content: 'Test content',
              refusal: null,
            },
            finish_reason: null,
            logprobs: null,
          },
        ],
        usage: {
          prompt_tokens: 10,
          completion_tokens: 5,
          total_tokens: 15,
        },
      } as unknown as ChatCompletion;
      const result = translator.translate(response);
      expect(result.finishReason).toBeNull();
    });

    it('should handle response with undefined usage', () => {
      const response: ChatCompletion = {
        id: 'chatcmpl-123',
        object: 'chat.completion',
        created: 1677652288,
        model: 'gpt-4o',
        choices: [
          {
            index: 0,
            message: {
              role: 'assistant',
              content: 'Test content',
              refusal: null,
            },
            finish_reason: 'stop',
            logprobs: null,
          },
        ],
        usage: undefined as any,
      };

      const result = translator.translate(response);

      expect(result.promptTokens).toBe(0);
      expect(result.completionTokens).toBe(0);
      expect(result.totalTokens).toBe(0);
    });

    it('should handle response with empty choices', () => {
      const response: ChatCompletion = {
        id: 'chatcmpl-123',
        object: 'chat.completion',
        created: 1677652288,
        model: 'gpt-4o',
        choices: [],
        usage: {
          prompt_tokens: 10,
          completion_tokens: 0,
          total_tokens: 10,
        },
      };

      const result = translator.translate(response);

      expect(result.content).toBe('');
      expect(result.finishReason).toBeNull();
    });

    it('should serialize tool_calls to JSON when present', () => {
      const response: ChatCompletion = {
        id: 'chatcmpl-123',
        object: 'chat.completion',
        created: 1677652288,
        model: 'gpt-4o',
        choices: [
          {
            index: 0,
            message: {
              role: 'assistant',
              content: null,
              refusal: null,
              tool_calls: [
                {
                  id: 'call_123',
                  type: 'function',
                  function: {
                    name: 'get_weather',
                    arguments: '{"location": "Paris"}',
                  },
                },
              ],
            },
            finish_reason: 'tool_calls',
            logprobs: null,
          },
        ],
        usage: {
          prompt_tokens: 10,
          completion_tokens: 5,
          total_tokens: 15,
        },
      };

      const result = translator.translate(response);

      expect(result.content).toContain('get_weather');
      expect(result.finishReason).toBe('tool_calls');
    });

    it('should handle missing model in response', () => {
      const response: ChatCompletion = {
        id: 'chatcmpl-123',
        object: 'chat.completion',
        created: 1677652288,
        model: undefined as any,
        choices: [
          {
            index: 0,
            message: {
              role: 'assistant',
              content: 'Test',
              refusal: null,
            },
            finish_reason: 'stop',
            logprobs: null,
          },
        ],
      };

      const result = translator.translate(response);

      expect(result.model).toBe('unknown');
    });
  });

  describe('translateStreamChunk', () => {
    it('should extract content from stream chunk', () => {
      const chunk = {
        choices: [
          {
            delta: {
              content: 'Hello',
            },
          },
        ],
      };

      const result = translator.translateStreamChunk(chunk);

      expect(result).toBe('Hello');
    });

    it('should return empty string for empty chunk', () => {
      const chunk = {
        choices: [
          {
            delta: {},
          },
        ],
      };

      const result = translator.translateStreamChunk(chunk);

      expect(result).toBe('');
    });

    it('should return empty string for null chunk', () => {
      const result = translator.translateStreamChunk(null);
      expect(result).toBe('');
    });
  });
});

// Helper function to create mock responses
function createMockResponse(
  overrides: Partial<ChatCompletion['choices'][0]> = {},
): ChatCompletion {
  return {
    id: 'chatcmpl-123',
    object: 'chat.completion',
    created: 1677652288,
    model: 'gpt-4o',
    choices: [
      {
        index: 0,
        message: {
          role: 'assistant',
          content: 'Test content',
          refusal: null,
        },
        finish_reason: 'stop',
        logprobs: null,
        ...overrides,
      },
    ],
    usage: {
      prompt_tokens: 10,
      completion_tokens: 5,
      total_tokens: 15,
    },
  };
}
