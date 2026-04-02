/**
 * OpenAI Adapter v2
 *
 * Implementação do AIProvider para OpenAI usando SDK oficial.
 * Suporta fallback de API key e circuit breaker compartilhado.
 */

import { Injectable, Logger } from '@nestjs/common';
import OpenAI, { APIError, RateLimitError, AuthenticationError } from 'openai';
import {
  AIProvider,
  AIProviderMetadata,
} from '../../interfaces/ai-provider.interface';
import { AICompletionRequest } from '../../interfaces/ai-completion-request.dto';
import { AICompletionResponseDto } from '../../interfaces/ai-completion-response.dto';
import { OpenAIConfigService } from './openai.config';
import { OpenAITranslator } from './openai.translator';
import { GatewayErrorCode } from '../../../../common/interfaces/gateway-response.interface';
import {
  CircuitBreakerService,
  CircuitOpenException,
  CircuitState,
} from '../../../../shared/services/circuit-breaker';
import { getCircuitBreakerOptions } from '../../../../core/config/circuit-breaker.config';

/**
 * Erro customizado do provider com código padronizado
 */
export class OpenAIProviderError extends Error {
  constructor(
    public readonly code: GatewayErrorCode,
    message: string,
    public readonly retryable: boolean = false,
    public readonly originalError?: Error,
  ) {
    super(message);
    this.name = 'OpenAIProviderError';
  }
}

@Injectable()
export class OpenAIProviderAdapter implements AIProvider {
  private static readonly CIRCUIT_NAME = 'openai-provider';
  readonly name = 'openai';

  private readonly logger = new Logger(OpenAIProviderAdapter.name);
  private primaryClient: OpenAI;
  private fallbackClient: OpenAI | null = null;

  /**
   * Metadata do provider para discovery
   */
  static readonly metadata: AIProviderMetadata = {
    name: 'openai',
    description: 'OpenAI GPT models (GPT-4, GPT-4o, etc.)',
    supportedModels: [
      'gpt-4o',
      'gpt-4o-mini',
      'gpt-4-turbo',
      'gpt-4',
      'gpt-3.5-turbo',
    ],
    supportsStreaming: true,
  };

  constructor(
    private readonly config: OpenAIConfigService,
    private readonly translator: OpenAITranslator,
    private readonly circuitBreaker: CircuitBreakerService,
  ) {
    // Inicializa cliente principal
    this.primaryClient = new OpenAI({
      apiKey: this.config.getApiKey(),
      timeout: this.config.getTimeoutMs(),
      maxRetries: this.config.getMaxRetries(),
    });

    // Inicializa cliente de fallback se configurado
    const fallbackKey = this.config.getFallbackApiKey();
    if (fallbackKey) {
      this.fallbackClient = new OpenAI({
        apiKey: fallbackKey,
        timeout: this.config.getTimeoutMs(),
        maxRetries: this.config.getMaxRetries(),
      });
      this.logger.log('OpenAI fallback client initialized');
    }
  }

  /**
   * Executa completion e retorna resposta normalizada
   */
  async complete(
    request: AICompletionRequest,
  ): Promise<AICompletionResponseDto> {
    // Valida configuração
    if (!this.config.isConfigured()) {
      throw new OpenAIProviderError(
        'PROVIDER_AUTH_ERROR',
        'OPENAI_API_KEY not configured',
        false,
      );
    }

    // Use shared circuit breaker for API calls
    try {
      return await this.circuitBreaker.call(
        OpenAIProviderAdapter.CIRCUIT_NAME,
        () => this.executeWithFallback(request),
        getCircuitBreakerOptions('openai', {
          name: OpenAIProviderAdapter.CIRCUIT_NAME,
        }),
      );
    } catch (error) {
      if (error instanceof CircuitOpenException) {
        throw new OpenAIProviderError(
          'CIRCUIT_BREAKER_OPEN',
          'Circuit breaker is open - too many recent failures',
          true,
        );
      }
      throw this.mapError(error);
    }
  }

  async *stream(
    request: AICompletionRequest,
  ): AsyncGenerator<string, void, unknown> {
    const model = request.model ?? this.config.getDefaultModel();
    const stream = await this.primaryClient.chat.completions.create({
      model,
      messages: request.messages.map((m) => ({
        role: m.role,
        content: m.content,
        ...(m.name ? { name: m.name } : {}),
      })),
      temperature: request.temperature ?? 1,
      max_tokens: request.maxTokens,
      top_p: request.topP,
      frequency_penalty: request.frequencyPenalty,
      presence_penalty: request.presencePenalty,
      stream: true,
      ...(request.tools?.length ? { tools: request.tools } : {}),
    });

    for await (const chunk of stream) {
      const content = this.translator.translateStreamChunk(chunk);
      if (content !== '') {
        yield content;
      }
    }
  }

  async createEmbeddings(params: {
    input: string[];
    model?: string;
    dimensions?: number;
  }): Promise<{
    object: string;
    data: Array<{
      object: string;
      embedding: number[];
      index: number;
    }>;
    model: string;
    usage?: {
      prompt_tokens: number;
      total_tokens: number;
    };
  }> {
    if (!this.config.isConfigured()) {
      throw new OpenAIProviderError(
        'PROVIDER_AUTH_ERROR',
        'OPENAI_API_KEY not configured',
        false,
      );
    }

    const model = params.model ?? this.config.getConfig().embeddingModel;

    try {
      return await this.circuitBreaker.call(
        OpenAIProviderAdapter.CIRCUIT_NAME,
        () =>
          this.executeEmbeddingsWithFallback(
            params.input,
            model,
            params.dimensions,
          ),
        getCircuitBreakerOptions('openai', {
          name: OpenAIProviderAdapter.CIRCUIT_NAME,
        }),
      );
    } catch (error) {
      if (error instanceof CircuitOpenException) {
        throw new OpenAIProviderError(
          'CIRCUIT_BREAKER_OPEN',
          'Circuit breaker is open - too many recent failures',
          true,
        );
      }

      throw this.mapError(error);
    }
  }

  /**
   * Execute completion with fallback support
   */
  private async executeWithFallback(
    request: AICompletionRequest,
  ): Promise<AICompletionResponseDto> {
    try {
      return await this.executeCompletion(this.primaryClient, request);
    } catch (error) {
      // If primary fails and fallback exists, try fallback
      if (this.shouldUseFallback(error) && this.fallbackClient) {
        this.logger.warn(
          'Primary OpenAI key failed, trying fallback',
          (error as Error).message,
        );
        return await this.executeCompletion(this.fallbackClient, request);
      }
      throw error;
    }
  }

  private async executeEmbeddingsWithFallback(
    input: string[],
    model: string,
    dimensions?: number,
  ): Promise<{
    object: string;
    data: Array<{
      object: string;
      embedding: number[];
      index: number;
    }>;
    model: string;
    usage?: {
      prompt_tokens: number;
      total_tokens: number;
    };
  }> {
    try {
      return await this.executeEmbeddings(
        this.primaryClient,
        input,
        model,
        dimensions,
      );
    } catch (error) {
      if (this.shouldUseFallback(error) && this.fallbackClient) {
        this.logger.warn(
          'Primary OpenAI key failed for embeddings, trying fallback',
          (error as Error).message,
        );
        return await this.executeEmbeddings(
          this.fallbackClient,
          input,
          model,
          dimensions,
        );
      }

      throw error;
    }
  }

  private async executeEmbeddings(
    client: OpenAI,
    input: string[],
    model: string,
    dimensions?: number,
  ): Promise<{
    object: string;
    data: Array<{
      object: string;
      embedding: number[];
      index: number;
    }>;
    model: string;
    usage?: {
      prompt_tokens: number;
      total_tokens: number;
    };
  }> {
    const response = await client.embeddings.create({
      model,
      input,
      dimensions,
      encoding_format: 'float',
    });

    return {
      object: response.object,
      data: response.data.map((item) => ({
        object: item.object,
        embedding: item.embedding,
        index: item.index,
      })),
      model: response.model,
      usage: response.usage
        ? {
            prompt_tokens: response.usage.prompt_tokens,
            total_tokens: response.usage.total_tokens,
          }
        : undefined,
    };
  }

  /**
   * Executa a chamada ao OpenAI
   */
  private async executeCompletion(
    client: OpenAI,
    request: AICompletionRequest,
  ): Promise<AICompletionResponseDto> {
    const model = request.model ?? this.config.getDefaultModel();

    this.logger.debug(
      `Calling OpenAI completion with model: ${model}, messages: ${request.messages.length}`,
    );

    const response = await client.chat.completions.create({
      model,
      messages: request.messages.map((m) => ({
        role: m.role,
        content: m.content,
        ...(m.name ? { name: m.name } : {}),
      })),
      temperature: request.temperature ?? 1,
      max_tokens: request.maxTokens,
      top_p: request.topP,
      frequency_penalty: request.frequencyPenalty,
      presence_penalty: request.presencePenalty,
      ...(request.tools?.length ? { tools: request.tools } : {}),
    });

    // Traduz resposta
    const result = this.translator.translate(response);

    this.logger.debug(
      `OpenAI completion success: ${result.totalTokens} tokens, finish: ${result.finishReason}`,
    );

    return result;
  }

  /**
   * Verifica se deve tentar fallback
   */
  private shouldUseFallback(error: unknown): boolean {
    if (!this.fallbackClient) return false;

    // Usa fallback em caso de rate limit ou server error
    if (error instanceof RateLimitError) return true;
    if (error instanceof APIError) {
      const status = error.status as number | undefined;
      return status !== undefined && (status === 429 || status >= 500);
    }

    return false;
  }

  /**
   * Mapeia erros do OpenAI para nossos códigos padronizados
   */
  private mapError(error: unknown): OpenAIProviderError {
    // Timeout
    if (error instanceof Error && error.message.includes('timeout')) {
      return new OpenAIProviderError(
        'PROVIDER_TIMEOUT',
        `OpenAI request timed out after ${this.config.getTimeoutMs()}ms`,
        true,
        error,
      );
    }

    // Rate limit
    if (error instanceof RateLimitError) {
      return new OpenAIProviderError(
        'PROVIDER_RATE_LIMIT',
        'OpenAI rate limit exceeded',
        true,
        error,
      );
    }

    // Auth error
    if (error instanceof AuthenticationError) {
      return new OpenAIProviderError(
        'PROVIDER_AUTH_ERROR',
        'OpenAI authentication failed - check API key',
        false,
        error,
      );
    }

    // API errors
    if (error instanceof APIError) {
      const status = error.status as number | undefined;

      // Content filter
      if (error.message.toLowerCase().includes('content_filter')) {
        return new OpenAIProviderError(
          'PROVIDER_CONTENT_FILTER',
          'Content was blocked by OpenAI content filter',
          false,
          error,
        );
      }

      // Server error
      if (status !== undefined && status >= 500) {
        return new OpenAIProviderError(
          'PROVIDER_SERVER_ERROR',
          `OpenAI server error: ${error.message}`,
          true,
          error,
        );
      }

      // Other client errors
      return new OpenAIProviderError(
        'INVALID_REQUEST',
        `OpenAI API error: ${error.message}`,
        false,
        error,
      );
    }

    // Unknown error
    return new OpenAIProviderError(
      'INTERNAL_ERROR',
      (error as Error).message ?? 'Unknown error',
      false,
      error as Error,
    );
  }

  /**
   * Verifica se o provider está saudável
   */
  async isHealthy(): Promise<boolean> {
    // Check shared circuit breaker state
    const circuitState = this.circuitBreaker.getState(
      OpenAIProviderAdapter.CIRCUIT_NAME,
    );
    if (circuitState === CircuitState.OPEN) {
      return false;
    }

    if (!this.config.isConfigured()) {
      return false;
    }

    try {
      // Faz uma chamada simples para verificar conectividade
      await this.primaryClient.models.list();
      return true;
    } catch {
      return false;
    }
  }
}
