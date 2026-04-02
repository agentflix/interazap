import {
  GeminiProviderAdapter,
  GeminiProviderError,
} from './gemini-provider.adapter';
import { GeminiConfigService } from './gemini.config';
import { GeminiTranslator } from './gemini.translator';
import { CircuitBreakerService } from '../../../../shared/services/circuit-breaker';
import { AICompletionRequest } from '../../interfaces/ai-completion-request.dto';

// Mock the Google SDK
const mockGenerateContent = jest.fn();
const mockGetGenerativeModel = jest.fn(() => ({
  generateContent: mockGenerateContent,
}));

jest.mock('@google/generative-ai', () => ({
  GoogleGenerativeAI: jest.fn(() => ({
    getGenerativeModel: mockGetGenerativeModel,
  })),
}));

describe('GeminiProviderAdapter', () => {
  let adapter: GeminiProviderAdapter;
  let mockConfig: jest.Mocked<
    Pick<
      GeminiConfigService,
      'isConfigured' | 'getDefaultModel' | 'getApiKey' | 'getTimeoutMs'
    >
  >;
  let mockTranslator: jest.Mocked<Pick<GeminiTranslator, 'translate'>>;
  let mockCircuitBreaker: jest.Mocked<Pick<CircuitBreakerService, 'call'>>;

  beforeEach(() => {
    jest.clearAllMocks();

    mockConfig = {
      isConfigured: jest.fn().mockReturnValue(true),
      getDefaultModel: jest.fn().mockReturnValue('gemini-2.5-flash'),
      getApiKey: jest.fn().mockReturnValue('test-api-key'),
      getTimeoutMs: jest.fn().mockReturnValue(180000),
    };

    mockTranslator = {
      translate: jest.fn().mockReturnValue({
        content: 'Gemini response',
        promptTokens: 10,
        completionTokens: 20,
        totalTokens: 30,
        model: 'gemini-2.5-flash',
        finishReason: 'stop',
      }),
    };

    mockCircuitBreaker = {
      call: jest
        .fn()
        .mockImplementation((_name: string, fn: () => Promise<unknown>) =>
          fn(),
        ),
    };

    adapter = new GeminiProviderAdapter(
      mockConfig as unknown as GeminiConfigService,
      mockTranslator as unknown as GeminiTranslator,
      mockCircuitBreaker as unknown as CircuitBreakerService,
    );

    mockGenerateContent.mockResolvedValue({
      response: {
        text: () => 'Gemini response',
        candidates: [
          {
            content: { parts: [{ text: 'Gemini response' }] },
            finishReason: 'STOP',
          },
        ],
        usageMetadata: {
          promptTokenCount: 10,
          candidatesTokenCount: 20,
          totalTokenCount: 30,
        },
      },
    });
  });

  it("should have provider name 'google'", () => {
    expect(adapter.name).toBe('google');
  });

  it('should use Google default model when completion request does not provide one', async () => {
    const request: AICompletionRequest = {
      messages: [{ role: 'user', content: 'Hello' }],
    };

    await adapter.complete(request);

    expect(mockConfig.getDefaultModel).toHaveBeenCalled();
    expect(mockGetGenerativeModel).toHaveBeenCalledWith(
      expect.objectContaining({ model: 'gemini-2.5-flash' }),
    );
  });

  it('should map AICompletionRequest messages to Google Gemini request format', async () => {
    const request: AICompletionRequest = {
      messages: [
        { role: 'system', content: 'You are a helpful assistant' },
        { role: 'user', content: 'Hello' },
        { role: 'assistant', content: 'Hi there' },
        { role: 'user', content: 'How are you?' },
      ],
    };

    await adapter.complete(request);

    // System message should be set as systemInstruction, not in contents
    expect(mockGetGenerativeModel).toHaveBeenCalledWith(
      expect.objectContaining({
        systemInstruction: 'You are a helpful assistant',
      }),
    );

    // Non-system messages should be converted: user→user, assistant→model
    expect(mockGenerateContent).toHaveBeenCalledWith(
      expect.objectContaining({
        contents: [
          { role: 'user', parts: [{ text: 'Hello' }] },
          { role: 'model', parts: [{ text: 'Hi there' }] },
          { role: 'user', parts: [{ text: 'How are you?' }] },
        ],
      }),
    );
  });

  it('should throw GeminiProviderError when not configured', async () => {
    mockConfig.isConfigured.mockReturnValue(false);

    const request: AICompletionRequest = {
      messages: [{ role: 'user', content: 'Hello' }],
    };

    await expect(adapter.complete(request)).rejects.toThrow(
      GeminiProviderError,
    );
    await expect(adapter.complete(request)).rejects.toThrow(
      'GOOGLE_AI_API_KEY not configured',
    );
  });

  it('should use provided model when request specifies one', async () => {
    const request: AICompletionRequest = {
      messages: [{ role: 'user', content: 'Hello' }],
      model: 'gemini-2.5-pro',
    };

    await adapter.complete(request);

    expect(mockGetGenerativeModel).toHaveBeenCalledWith(
      expect.objectContaining({ model: 'gemini-2.5-pro' }),
    );
  });
});
