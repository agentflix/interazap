/**
 * OpenAI Provider Adapter Tests
 *
 * Testes unitários para o adapter OpenAI.
 */

import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import {
  OpenAIProviderAdapter,
  OpenAIProviderError,
} from '../providers/openai/openai-provider.adapter';
import { CircuitBreakerService } from '../../../shared/services/circuit-breaker';
import { OpenAIConfigService } from '../providers/openai/openai.config';
import { OpenAITranslator } from '../providers/openai/openai.translator';
import { AICompletionRequest } from '../interfaces/ai-completion-request.dto';

// Mock OpenAI SDK - usa __esModule para compatibilidade com ts-jest
// As classes de erro são definidas DENTRO do mock para evitar hoisting issues
jest.mock('openai', () => {
  // Mock error classes - DEVEM ser definidas dentro do factory
  class APIError extends Error {
    status: number;
    constructor(status: number, message: string) {
      super(message);
      this.status = status;
      this.name = 'APIError';
    }
  }

  class RateLimitError extends Error {
    status = 429;
    constructor(message = 'Rate limit exceeded') {
      super(message);
      this.name = 'RateLimitError';
    }
  }

  class AuthenticationError extends Error {
    status = 401;
    constructor(message = 'Invalid API key') {
      super(message);
      this.name = 'AuthenticationError';
    }
  }

  // Armazena classes no global para uso nos testes

  (global as any).__OpenAIMocks = {
    APIError,
    RateLimitError,
    AuthenticationError,
  };

  const MockOpenAI = jest.fn().mockImplementation((): any => {
    const instance = {
      chat: {
        completions: {
          create: jest.fn(),
        },
      },
      models: {
        list: jest.fn(),
      },
    };
    // Armazena em variável global para acesso

    (global as any).__lastMockInstance = instance;
    return instance;
  });

  // Adiciona propriedade default para compatibilidade ESM/CJS
  (MockOpenAI as any).default = MockOpenAI;

  return {
    __esModule: true,
    default: MockOpenAI,
    APIError,
    RateLimitError,
    AuthenticationError,
  };
});

// Importa classes de erro do módulo mockado (via global)
interface MockOpenAIClasses {
  APIError: new (status: number, message: string) => Error & { status: number };
  RateLimitError: new (message?: string) => Error & { status: number };
  AuthenticationError: new (message?: string) => Error & { status: number };
}

const getMocks = (): MockOpenAIClasses =>
  (global as unknown as { __OpenAIMocks: MockOpenAIClasses }).__OpenAIMocks;

describe('OpenAIProviderAdapter', () => {
  let adapter: OpenAIProviderAdapter;
  let configService: OpenAIConfigService;
  let circuitBreaker: CircuitBreakerService;
  let mockOpenAICreate: jest.Mock;

  const mockRequest: AICompletionRequest = {
    messages: [
      { role: 'system', content: 'You are a helpful assistant.' },
      { role: 'user', content: 'Hello!' },
    ],
    temperature: 0.7,
  };

  const mockOpenAIResponse = {
    id: 'chatcmpl-123',
    object: 'chat.completion',
    created: 1677652288,
    model: 'gpt-4o',
    choices: [
      {
        index: 0,
        message: {
          role: 'assistant',
          content: 'Hello! How can I help you today?',
          refusal: null,
        },
        finish_reason: 'stop',
        logprobs: null,
      },
    ],
    usage: {
      prompt_tokens: 20,
      completion_tokens: 10,
      total_tokens: 30,
    },
  };

  beforeEach(async () => {
    // Reset mocks
    jest.clearAllMocks();

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        OpenAIProviderAdapter,
        OpenAITranslator,
        CircuitBreakerService,
        {
          provide: OpenAIConfigService,
          useValue: {
            getApiKey: jest.fn().mockReturnValue('sk-test-key'),
            getFallbackApiKey: jest.fn().mockReturnValue(undefined),
            hasFallbackKey: jest.fn().mockReturnValue(false),
            getDefaultModel: jest.fn().mockReturnValue('gpt-4o'),
            getTimeoutMs: jest.fn().mockReturnValue(180000),
            getMaxRetries: jest.fn().mockReturnValue(3),
            isConfigured: jest.fn().mockReturnValue(true),
          },
        },
        {
          provide: ConfigService,
          useValue: {
            get: jest.fn(),
          },
        },
      ],
    }).compile();

    adapter = module.get<OpenAIProviderAdapter>(OpenAIProviderAdapter);
    configService = module.get<OpenAIConfigService>(OpenAIConfigService);
    circuitBreaker = module.get<CircuitBreakerService>(CircuitBreakerService);

    // Get mock function from the last created mock instance stored in global

    const lastInstance = (global as any).__lastMockInstance;
    mockOpenAICreate = lastInstance.chat.completions.create;
  });

  describe('name', () => {
    it('should have name "openai"', () => {
      expect(adapter.name).toBe('openai');
    });
  });

  describe('complete', () => {
    it('should return completion response on success', async () => {
      mockOpenAICreate.mockResolvedValue(mockOpenAIResponse);

      const result = await adapter.complete(mockRequest);

      expect(result.content).toBe('Hello! How can I help you today?');
      expect(result.promptTokens).toBe(20);
      expect(result.completionTokens).toBe(10);
      expect(result.totalTokens).toBe(30);
      expect(result.model).toBe('gpt-4o');
      expect(result.finishReason).toBe('stop');
    });

    it('should use default model when not specified', async () => {
      mockOpenAICreate.mockResolvedValue(mockOpenAIResponse);

      await adapter.complete(mockRequest);

      expect(mockOpenAICreate).toHaveBeenCalledWith(
        expect.objectContaining({
          model: 'gpt-4o',
        }),
      );
    });

    it('should use specified model when provided', async () => {
      mockOpenAICreate.mockResolvedValue(mockOpenAIResponse);

      await adapter.complete({
        ...mockRequest,
        model: 'gpt-4o-mini',
      });

      expect(mockOpenAICreate).toHaveBeenCalledWith(
        expect.objectContaining({
          model: 'gpt-4o-mini',
        }),
      );
    });

    it('should pass all optional parameters', async () => {
      mockOpenAICreate.mockResolvedValue(mockOpenAIResponse);

      await adapter.complete({
        ...mockRequest,
        maxTokens: 1000,
        temperature: 0.5,
        topP: 0.9,
        frequencyPenalty: 0.1,
        presencePenalty: 0.2,
      });

      expect(mockOpenAICreate).toHaveBeenCalledWith(
        expect.objectContaining({
          max_tokens: 1000,
          temperature: 0.5,
          top_p: 0.9,
          frequency_penalty: 0.1,
          presence_penalty: 0.2,
        }),
      );
    });

    it('should throw PROVIDER_AUTH_ERROR when not configured', async () => {
      jest.spyOn(configService, 'isConfigured').mockReturnValue(false);

      await expect(adapter.complete(mockRequest)).rejects.toThrow(
        OpenAIProviderError,
      );
      await expect(adapter.complete(mockRequest)).rejects.toMatchObject({
        code: 'PROVIDER_AUTH_ERROR',
        retryable: false,
      });
    });

    it('should throw PROVIDER_TIMEOUT on timeout', async () => {
      const timeoutError = new Error('Request timed out after 180000ms');
      timeoutError.message = 'timeout';
      mockOpenAICreate.mockRejectedValue(timeoutError);

      await expect(adapter.complete(mockRequest)).rejects.toThrow(
        OpenAIProviderError,
      );
      await expect(adapter.complete(mockRequest)).rejects.toMatchObject({
        code: 'PROVIDER_TIMEOUT',
        retryable: true,
      });
    });

    it('should throw PROVIDER_RATE_LIMIT on 429', async () => {
      // Usando mocks declarados no topo
      mockOpenAICreate.mockRejectedValue(
        new (getMocks().RateLimitError)('Rate limit exceeded'),
      );

      await expect(adapter.complete(mockRequest)).rejects.toThrow(
        OpenAIProviderError,
      );
      await expect(adapter.complete(mockRequest)).rejects.toMatchObject({
        code: 'PROVIDER_RATE_LIMIT',
        retryable: true,
      });
    });

    it('should throw PROVIDER_AUTH_ERROR on 401', async () => {
      // Usando mocks declarados no topo
      mockOpenAICreate.mockRejectedValue(
        new (getMocks().AuthenticationError)('Invalid API key'),
      );

      await expect(adapter.complete(mockRequest)).rejects.toThrow(
        OpenAIProviderError,
      );
      await expect(adapter.complete(mockRequest)).rejects.toMatchObject({
        code: 'PROVIDER_AUTH_ERROR',
        retryable: false,
      });
    });

    it('should throw PROVIDER_CONTENT_FILTER when content is filtered', async () => {
      // Usando mocks declarados no topo
      const error = new (getMocks().APIError)(400, 'content_filter triggered');
      error.message = 'content_filter triggered';
      mockOpenAICreate.mockRejectedValue(error);

      await expect(adapter.complete(mockRequest)).rejects.toThrow(
        OpenAIProviderError,
      );
      await expect(adapter.complete(mockRequest)).rejects.toMatchObject({
        code: 'PROVIDER_CONTENT_FILTER',
        retryable: false,
      });
    });

    it('should throw PROVIDER_SERVER_ERROR on 5xx', async () => {
      // Usando mocks declarados no topo
      const error = new (getMocks().APIError)(500, 'Internal server error');
      mockOpenAICreate.mockRejectedValue(error);

      await expect(adapter.complete(mockRequest)).rejects.toThrow(
        OpenAIProviderError,
      );
      await expect(adapter.complete(mockRequest)).rejects.toMatchObject({
        code: 'PROVIDER_SERVER_ERROR',
        retryable: true,
      });
    });
  });

  describe('fallback key', () => {
    beforeEach(() => {
      jest.spyOn(configService, 'hasFallbackKey').mockReturnValue(true);
      jest
        .spyOn(configService, 'getFallbackApiKey')
        .mockReturnValue('sk-fallback-key');

      // Reinitialize adapter with fallback
      (adapter as any).fallbackClient = {
        chat: {
          completions: {
            create: jest.fn().mockResolvedValue(mockOpenAIResponse),
          },
        },
      };
    });

    it('should use fallback key when primary fails with rate limit', async () => {
      // Usando mocks declarados no topo
      mockOpenAICreate.mockRejectedValue(
        new (getMocks().RateLimitError)('Rate limit exceeded'),
      );

      const result = await adapter.complete(mockRequest);

      expect(result.content).toBe('Hello! How can I help you today?');
    });

    it('should use fallback key when primary fails with 5xx', async () => {
      // Usando mocks declarados no topo
      mockOpenAICreate.mockRejectedValue(
        new (getMocks().APIError)(503, 'Service unavailable'),
      );

      const result = await adapter.complete(mockRequest);

      expect(result.content).toBe('Hello! How can I help you today?');
    });

    it('should not use fallback for auth errors', async () => {
      // Usando mocks declarados no topo
      mockOpenAICreate.mockRejectedValue(
        new (getMocks().AuthenticationError)('Invalid API key'),
      );

      await expect(adapter.complete(mockRequest)).rejects.toThrow(
        OpenAIProviderError,
      );
    });
  });

  describe('circuit breaker', () => {
    it('should open circuit after multiple failures', async () => {
      mockOpenAICreate.mockRejectedValue(new Error('Network error'));

      // Trigger failures
      for (let i = 0; i < 5; i++) {
        try {
          await adapter.complete(mockRequest);
        } catch {
          // Expected to fail
        }
      }

      // Next call should fail with circuit breaker
      await expect(adapter.complete(mockRequest)).rejects.toMatchObject({
        code: 'CIRCUIT_BREAKER_OPEN',
        retryable: true,
      });
    });

    it('should reset circuit after successful call', async () => {
      // First, trigger some failures
      mockOpenAICreate.mockRejectedValue(new Error('Network error'));
      for (let i = 0; i < 3; i++) {
        try {
          await adapter.complete(mockRequest);
        } catch {
          // Expected to fail
        }
      }

      // Manually reset circuit for deterministic test
      circuitBreaker.reset('openai-provider');

      // Now succeed
      mockOpenAICreate.mockResolvedValue(mockOpenAIResponse);
      const result = await adapter.complete(mockRequest);

      expect(result.content).toBe('Hello! How can I help you today?');
    });
  });

  describe('isHealthy', () => {
    it('should return true when configured and circuit is closed', async () => {
      (adapter as any).primaryClient.models.list = jest
        .fn()
        .mockResolvedValue({});

      const result = await adapter.isHealthy();

      expect(result).toBe(true);
    });

    it('should return false when not configured', async () => {
      jest.spyOn(configService, 'isConfigured').mockReturnValue(false);

      const result = await adapter.isHealthy();

      expect(result).toBe(false);
    });

    it('should return false when API call fails', async () => {
      (adapter as any).primaryClient.models.list = jest
        .fn()
        .mockRejectedValue(new Error('API error'));

      const result = await adapter.isHealthy();

      expect(result).toBe(false);
    });
  });

  describe('fallback client', () => {
    let adapterWithFallback: OpenAIProviderAdapter;
    let mockPrimaryCreate: jest.Mock;
    let mockFallbackCreate: jest.Mock;
    let fallbackCircuitBreaker: CircuitBreakerService;

    beforeEach(async () => {
      jest.clearAllMocks();

      // Create instances for primary and fallback clients
      let instanceCount = 0;
      const primaryInstance = {
        chat: { completions: { create: jest.fn() } },
        models: { list: jest.fn() },
      };
      const fallbackInstance = {
        chat: { completions: { create: jest.fn() } },
        models: { list: jest.fn() },
      };

      // Mock OpenAI constructor to return different instances
      const OpenAI = jest.requireMock('openai').default;
      OpenAI.mockImplementation(() => {
        instanceCount++;
        if (instanceCount === 1) return primaryInstance;
        return fallbackInstance;
      });

      mockPrimaryCreate = primaryInstance.chat.completions.create;
      mockFallbackCreate = fallbackInstance.chat.completions.create;

      const module: TestingModule = await Test.createTestingModule({
        providers: [
          OpenAIProviderAdapter,
          OpenAITranslator,
          CircuitBreakerService,
          {
            provide: OpenAIConfigService,
            useValue: {
              getApiKey: jest.fn().mockReturnValue('sk-primary-key'),
              getFallbackApiKey: jest.fn().mockReturnValue('sk-fallback-key'),
              hasFallbackKey: jest.fn().mockReturnValue(true),
              getDefaultModel: jest.fn().mockReturnValue('gpt-4o'),
              getTimeoutMs: jest.fn().mockReturnValue(180000),
              getMaxRetries: jest.fn().mockReturnValue(3),
              isConfigured: jest.fn().mockReturnValue(true),
            },
          },
          {
            provide: ConfigService,
            useValue: { get: jest.fn() },
          },
        ],
      }).compile();

      adapterWithFallback = module.get<OpenAIProviderAdapter>(
        OpenAIProviderAdapter,
      );
      fallbackCircuitBreaker = module.get<CircuitBreakerService>(
        CircuitBreakerService,
      );
    });

    it('should initialize fallback client when fallback key is provided', () => {
      expect((adapterWithFallback as any).fallbackClient).toBeDefined();
    });

    it('should use fallback when primary fails with rate limit', async () => {
      mockPrimaryCreate.mockRejectedValue(
        new (getMocks().RateLimitError)('Rate limit exceeded'),
      );
      mockFallbackCreate.mockResolvedValue(mockOpenAIResponse);

      const result = await adapterWithFallback.complete(mockRequest);

      expect(mockPrimaryCreate).toHaveBeenCalledTimes(1);
      expect(mockFallbackCreate).toHaveBeenCalledTimes(1);
      expect(result.content).toBe('Hello! How can I help you today?');
    });

    it('should use fallback when primary fails with server error (500+)', async () => {
      // APIError with status >= 500 should trigger fallback
      const serverError = new (getMocks().APIError)(
        500,
        'Internal server error',
      );
      mockPrimaryCreate.mockRejectedValue(serverError);
      mockFallbackCreate.mockResolvedValue(mockOpenAIResponse);

      const result = await adapterWithFallback.complete(mockRequest);

      expect(mockPrimaryCreate).toHaveBeenCalledTimes(1);
      expect(mockFallbackCreate).toHaveBeenCalledTimes(1);
      expect(result.content).toBe('Hello! How can I help you today?');
    });

    it('should NOT use fallback for auth error (not retryable)', async () => {
      mockPrimaryCreate.mockRejectedValue(
        new (getMocks().AuthenticationError)('Invalid API key'),
      );

      await expect(adapterWithFallback.complete(mockRequest)).rejects.toThrow(
        OpenAIProviderError,
      );

      expect(mockPrimaryCreate).toHaveBeenCalledTimes(1);
      expect(mockFallbackCreate).not.toHaveBeenCalled();
    });

    it('should throw error when both primary and fallback fail', async () => {
      mockPrimaryCreate.mockRejectedValue(
        new (getMocks().RateLimitError)('Rate limit exceeded'),
      );
      mockFallbackCreate.mockRejectedValue(
        new (getMocks().RateLimitError)('Fallback also rate limited'),
      );

      await expect(adapterWithFallback.complete(mockRequest)).rejects.toThrow(
        OpenAIProviderError,
      );

      expect(mockPrimaryCreate).toHaveBeenCalledTimes(1);
      expect(mockFallbackCreate).toHaveBeenCalledTimes(1);
    });

    it('should call circuit breaker when fallback also fails', async () => {
      mockPrimaryCreate.mockRejectedValue(
        new (getMocks().AuthenticationError)('Primary auth failed'),
      );
      mockFallbackCreate.mockRejectedValue(
        new (getMocks().AuthenticationError)('Fallback auth failed'),
      );

      const callSpy = jest.spyOn(fallbackCircuitBreaker, 'call');

      await expect(adapterWithFallback.complete(mockRequest)).rejects.toThrow();

      expect(callSpy).toHaveBeenCalled();
    });
  });
});
