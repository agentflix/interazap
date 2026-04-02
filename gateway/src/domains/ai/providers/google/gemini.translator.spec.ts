import { GeminiTranslator } from './gemini.translator';
import type { GenerateContentResult } from '@google/generative-ai';

describe('GeminiTranslator', () => {
  let translator: GeminiTranslator;

  beforeEach(() => {
    translator = new GeminiTranslator();
  });

  it('should translate Google Gemini responses into normalized completion DTOs', () => {
    const mockResponse: GenerateContentResult = {
      response: {
        text: () => 'Hello from Gemini!',
        candidates: [
          {
            content: { role: 'model', parts: [{ text: 'Hello from Gemini!' }] },
            finishReason: 'STOP',
            index: 0,
          },
        ],
        usageMetadata: {
          promptTokenCount: 10,
          candidatesTokenCount: 20,
          totalTokenCount: 30,
        },
      },
    } as unknown as GenerateContentResult;

    const result = translator.translate(mockResponse, 'gemini-2.5-flash');

    expect(result.content).toBe('Hello from Gemini!');
    expect(result.promptTokens).toBe(10);
    expect(result.completionTokens).toBe(20);
    expect(result.totalTokens).toBe(30);
    expect(result.model).toBe('gemini-2.5-flash');
    expect(result.finishReason).toBe('stop');
  });

  it('should normalize Google Gemini finish reasons to internal values', () => {
    const makeResponse = (finishReason: string): GenerateContentResult =>
      ({
        response: {
          text: () => 'text',
          candidates: [
            {
              content: { role: 'model', parts: [{ text: 'text' }] },
              finishReason,
              index: 0,
            },
          ],
          usageMetadata: {
            promptTokenCount: 0,
            candidatesTokenCount: 0,
            totalTokenCount: 0,
          },
        },
      }) as unknown as GenerateContentResult;

    expect(translator.translate(makeResponse('STOP'), 'm').finishReason).toBe(
      'stop',
    );
    expect(
      translator.translate(makeResponse('MAX_TOKENS'), 'm').finishReason,
    ).toBe('length');
    expect(translator.translate(makeResponse('SAFETY'), 'm').finishReason).toBe(
      'content_filter',
    );
    expect(
      translator.translate(makeResponse('RECITATION'), 'm').finishReason,
    ).toBe('content_filter');
  });

  it('should handle empty content gracefully', () => {
    const mockResponse: GenerateContentResult = {
      response: {
        text: () => {
          throw new Error('No content');
        },
        candidates: [
          {
            content: { role: 'model', parts: [] },
            finishReason: 'STOP',
            index: 0,
          },
        ],
        usageMetadata: {
          promptTokenCount: 5,
          candidatesTokenCount: 0,
          totalTokenCount: 5,
        },
      },
    } as unknown as GenerateContentResult;

    const result = translator.translate(mockResponse, 'gemini-2.5-flash');

    expect(result.content).toBe('');
    expect(result.model).toBe('gemini-2.5-flash');
  });

  it('should return null finishReason when no candidates exist', () => {
    const mockResponse: GenerateContentResult = {
      response: {
        text: () => '',
        candidates: [],
        usageMetadata: {
          promptTokenCount: 0,
          candidatesTokenCount: 0,
          totalTokenCount: 0,
        },
      },
    } as unknown as GenerateContentResult;

    const result = translator.translate(mockResponse, 'gemini-2.5-flash');

    expect(result.finishReason).toBeNull();
  });
});
