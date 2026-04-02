import {
  MiniMaxProviderAdapter,
  MiniMaxProviderError,
} from './minimax-provider.adapter';
import { MiniMaxConfigService } from './minimax.config';
import { MiniMaxTranslator } from './minimax.translator';
import { CircuitBreakerService } from '../../../../shared/services/circuit-breaker';
import { AICompletionRequest } from '../../interfaces/ai-completion-request.dto';

/**
 * Testes do MiniMaxProviderAdapter
 *
 * Verifica wiring, validação de config e comportamento do adapter.
 */
describe('MiniMaxProviderAdapter', () => {
  let adapter: MiniMaxProviderAdapter;
  let mockConfig: jest.Mocked<
    Pick<
      MiniMaxConfigService,
      | 'isConfigured'
      | 'getDefaultModel'
      | 'getApiKey'
      | 'getBaseUrl'
      | 'getTimeoutMs'
    >
  >;
  let mockTranslator: jest.Mocked<Pick<MiniMaxTranslator, 'translate'>>;
  let mockCircuitBreaker: jest.Mocked<Pick<CircuitBreakerService, 'call'>>;

  beforeEach(() => {
    jest.clearAllMocks();

    mockConfig = {
      isConfigured: jest.fn().mockReturnValue(true),
      getDefaultModel: jest.fn().mockReturnValue('MiniMax-M2.5'),
      getApiKey: jest.fn().mockReturnValue('test-api-key'),
      getBaseUrl: jest.fn().mockReturnValue('https://api.minimax.io'),
      getTimeoutMs: jest.fn().mockReturnValue(180000),
    };

    mockTranslator = {
      translate: jest.fn().mockReturnValue({
        content: 'MiniMax response',
        promptTokens: 10,
        completionTokens: 20,
        totalTokens: 30,
        model: 'MiniMax-M2.5',
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

    adapter = new MiniMaxProviderAdapter(
      mockConfig as unknown as MiniMaxConfigService,
      mockTranslator as unknown as MiniMaxTranslator,
      mockCircuitBreaker as unknown as CircuitBreakerService,
    );
  });

  it("should have provider name 'minimax'", () => {
    expect(adapter.name).toBe('minimax');
  });

  it('should throw MiniMaxProviderError when not configured', async () => {
    mockConfig.isConfigured.mockReturnValue(false);

    const request: AICompletionRequest = {
      messages: [{ role: 'user', content: 'Hello' }],
    };

    await expect(adapter.complete(request)).rejects.toThrow(
      MiniMaxProviderError,
    );
    await expect(adapter.complete(request)).rejects.toThrow(
      'MINIMAX_API_KEY not configured',
    );
  });

  it('should use default model when request does not specify one', async () => {
    const mockFetch = jest.fn().mockResolvedValue({
      ok: true,
      json: () =>
        Promise.resolve({
          id: 'chatcmpl-123',
          object: 'chat.completion',
          created: 1700000000,
          model: 'MiniMax-M2.5',
          choices: [
            {
              index: 0,
              message: { role: 'assistant', content: 'response' },
              finish_reason: 'stop',
            },
          ],
          usage: { prompt_tokens: 5, completion_tokens: 10, total_tokens: 15 },
        }),
    });
    global.fetch = mockFetch;

    const request: AICompletionRequest = {
      messages: [{ role: 'user', content: 'Hello' }],
    };

    await adapter.complete(request);

    expect(mockConfig.getDefaultModel).toHaveBeenCalled();
    expect(mockFetch).toHaveBeenCalledWith(
      'https://api.minimax.io/v1/chat/completions',
      expect.objectContaining({
        method: 'POST',
        body: expect.stringContaining('"model":"MiniMax-M2.5"'),
      }),
    );
  });

  it('should have correct static metadata', () => {
    expect(MiniMaxProviderAdapter.metadata).toEqual({
      name: 'minimax',
      description: 'MiniMax models (M2.5, M2.5-highspeed)',
      supportedModels: ['MiniMax-M2.5', 'MiniMax-M2.5-highspeed'],
      supportsStreaming: false,
    });
  });
});
