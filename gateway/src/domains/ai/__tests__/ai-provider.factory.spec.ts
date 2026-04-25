/**
 * AI Provider Factory Tests
 *
 * Testes unitários para a factory de providers.
 */

import { Test, TestingModule } from '@nestjs/testing';
import {
  AIProviderFactory,
  UnknownProviderError,
} from '../providers/ai-provider.factory';
import { OpenAIProviderAdapter } from '../providers/openai/openai-provider.adapter';
import { GeminiProviderAdapter } from '../providers/google/gemini-provider.adapter';
import { MiniMaxProviderAdapter } from '../providers/minimax/minimax-provider.adapter';
import { AIProvider } from '../interfaces/ai-provider.interface';

describe('AIProviderFactory', () => {
  let factory: AIProviderFactory;
  let mockOpenAIAdapter: jest.Mocked<OpenAIProviderAdapter>;
  let mockGeminiAdapter: jest.Mocked<GeminiProviderAdapter>;
  let mockMiniMaxAdapter: jest.Mocked<MiniMaxProviderAdapter>;

  beforeEach(async () => {
    mockOpenAIAdapter = {
      name: 'openai',
      complete: jest.fn(),
      isHealthy: jest.fn(),
    } as any;

    mockGeminiAdapter = {
      name: 'google',
      complete: jest.fn(),
      isHealthy: jest.fn(),
    } as any;

    mockMiniMaxAdapter = {
      name: 'minimax',
      complete: jest.fn(),
      isHealthy: jest.fn(),
    } as any;

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        AIProviderFactory,
        {
          provide: OpenAIProviderAdapter,
          useValue: mockOpenAIAdapter,
        },
        {
          provide: GeminiProviderAdapter,
          useValue: mockGeminiAdapter,
        },
        {
          provide: MiniMaxProviderAdapter,
          useValue: mockMiniMaxAdapter,
        },
      ],
    }).compile();

    factory = module.get<AIProviderFactory>(AIProviderFactory);
  });

  describe('getProvider', () => {
    it('should return OpenAI adapter for "openai"', () => {
      const provider = factory.getProvider('openai');
      expect(provider).toBe(mockOpenAIAdapter);
      expect(provider.name).toBe('openai');
    });

    it('should be case insensitive - "OpenAI"', () => {
      const provider = factory.getProvider('OpenAI');
      expect(provider).toBe(mockOpenAIAdapter);
    });

    it('should be case insensitive - "OPENAI"', () => {
      const provider = factory.getProvider('OPENAI');
      expect(provider).toBe(mockOpenAIAdapter);
    });

    it('should throw UnknownProviderError for unknown provider', () => {
      expect(() => factory.getProvider('unknown')).toThrow(
        UnknownProviderError,
      );
      expect(() => factory.getProvider('unknown')).toThrow(
        "Unknown AI provider: 'unknown'",
      );
    });

    it('should throw UnknownProviderError for empty string', () => {
      expect(() => factory.getProvider('')).toThrow(UnknownProviderError);
    });

    it('should throw UnknownProviderError for gemini (not a valid provider name)', () => {
      expect(() => factory.getProvider('gemini')).toThrow(UnknownProviderError);
    });

    it('should return Gemini adapter for "google"', () => {
      const provider = factory.getProvider('google');
      expect(provider).toBe(mockGeminiAdapter);
      expect(provider.name).toBe('google');
    });
  });

  describe('hasProvider', () => {
    it('should return true for registered provider', () => {
      expect(factory.hasProvider('openai')).toBe(true);
    });

    it('should return false for unknown provider', () => {
      expect(factory.hasProvider('unknown')).toBe(false);
    });

    it('should be case insensitive', () => {
      expect(factory.hasProvider('OpenAI')).toBe(true);
      expect(factory.hasProvider('OPENAI')).toBe(true);
    });
  });

  describe('listProviders', () => {
    it('should list all registered providers', () => {
      const providers = factory.listProviders();
      expect(providers).toContain('openai');
      expect(providers).toContain('google');
      expect(providers.length).toBeGreaterThanOrEqual(2);
    });
  });

  describe('getDefaultProvider', () => {
    it('should return OpenAI as default provider', () => {
      const provider = factory.getDefaultProvider();
      expect(provider).toBe(mockOpenAIAdapter);
    });
  });

  describe('registerProvider', () => {
    it('should allow registering new providers dynamically', () => {
      const mockGeminiAdapter: AIProvider = {
        name: 'gemini',
        complete: jest.fn(),
      };

      factory.registerProvider(mockGeminiAdapter);

      expect(factory.hasProvider('gemini')).toBe(true);
      expect(factory.getProvider('gemini')).toBe(mockGeminiAdapter);
    });

    it('should allow overwriting existing providers', () => {
      const newOpenAIAdapter: AIProvider = {
        name: 'openai',
        complete: jest.fn(),
      };

      factory.registerProvider(newOpenAIAdapter);

      expect(factory.getProvider('openai')).toBe(newOpenAIAdapter);
    });
  });

  describe('checkHealth', () => {
    it('should return health status of all providers', async () => {
      mockOpenAIAdapter.isHealthy.mockResolvedValue(true);

      const health = await factory.checkHealth();

      expect(health.get('openai')).toBe(true);
    });

    it('should return false for unhealthy providers', async () => {
      mockOpenAIAdapter.isHealthy.mockResolvedValue(false);

      const health = await factory.checkHealth();

      expect(health.get('openai')).toBe(false);
    });

    it('should handle providers without isHealthy method', async () => {
      const mockProvider: AIProvider = {
        name: 'simple',
        complete: jest.fn(),
        // No isHealthy method
      };
      factory.registerProvider(mockProvider);

      const health = await factory.checkHealth();

      expect(health.get('simple')).toBe(true); // Default to healthy
    });

    it('should handle isHealthy throwing an error', async () => {
      mockOpenAIAdapter.isHealthy.mockRejectedValue(
        new Error('Health check failed'),
      );

      const health = await factory.checkHealth();

      expect(health.get('openai')).toBe(false);
    });
  });

  describe('UnknownProviderError', () => {
    it('should have correct properties', () => {
      const error = new UnknownProviderError('test');

      expect(error.code).toBe('UNKNOWN_PROVIDER');
      expect(error.retryable).toBe(false);
      expect(error.message).toBe("Unknown AI provider: 'test'");
    });

    it('should convert to GatewayError', () => {
      const error = new UnknownProviderError('test');
      const gatewayError = error.toGatewayError();

      expect(gatewayError.code).toBe('UNKNOWN_PROVIDER');
      expect(gatewayError.message).toBe("Unknown AI provider: 'test'");
      expect(gatewayError.retryable).toBe(false);
    });
  });
});
