/**
 * Gemini Provider Adapter
 *
 * Implementação do AIProvider para Google Gemini usando SDK oficial.
 * Suporta circuit breaker compartilhado.
 */

import { Injectable, Logger } from '@nestjs/common';
import { GoogleGenerativeAI } from '@google/generative-ai';
import type { Content } from '@google/generative-ai';
import {
  AIProvider,
  AIProviderMetadata,
} from '../../interfaces/ai-provider.interface';
import { AICompletionRequest } from '../../interfaces/ai-completion-request.dto';
import { AICompletionResponseDto } from '../../interfaces/ai-completion-response.dto';
import { GeminiConfigService } from './gemini.config';
import { GeminiTranslator } from './gemini.translator';
import { GatewayErrorCode } from '../../../../common/interfaces/gateway-response.interface';
import {
  CircuitBreakerService,
  CircuitOpenException,
} from '../../../../shared/services/circuit-breaker';
import { getCircuitBreakerOptions } from '../../../../core/config/circuit-breaker.config';

/**
 * Erro customizado do provider com código padronizado
 */
export class GeminiProviderError extends Error {
  constructor(
    public readonly code: GatewayErrorCode,
    message: string,
    public readonly retryable: boolean = false,
    public readonly originalError?: Error,
  ) {
    super(message);
    this.name = 'GeminiProviderError';
  }
}

@Injectable()
export class GeminiProviderAdapter implements AIProvider {
  private static readonly CIRCUIT_NAME = 'gemini-provider';
  readonly name = 'google';

  private readonly logger = new Logger(GeminiProviderAdapter.name);

  /**
   * Metadata do provider para discovery
   */
  static readonly metadata: AIProviderMetadata = {
    name: 'google',
    description: 'Google Gemini models (Gemini 2.5, 3.1, etc.)',
    supportedModels: [
      'gemini-2.5-pro',
      'gemini-2.5-flash',
      'gemini-3.1-pro-preview',
      'gemini-3.1-flash-lite-preview',
      'gemini-1.5-pro',
    ],
    supportsStreaming: false,
  };

  constructor(
    private readonly config: GeminiConfigService,
    private readonly translator: GeminiTranslator,
    private readonly circuitBreaker: CircuitBreakerService,
  ) {
    this.client = new GoogleGenerativeAI(this.config.getApiKey());
  }

  private readonly client: GoogleGenerativeAI;

  /**
   * Executa completion e retorna resposta normalizada
   */
  async complete(
    request: AICompletionRequest,
  ): Promise<AICompletionResponseDto> {
    if (!this.config.isConfigured()) {
      throw new GeminiProviderError(
        'PROVIDER_AUTH_ERROR',
        'GOOGLE_AI_API_KEY not configured',
        false,
      );
    }

    const model = request.model ?? this.config.getDefaultModel();

    try {
      return await this.circuitBreaker.call(
        GeminiProviderAdapter.CIRCUIT_NAME,
        () => this.executeCompletion(request, model),
        getCircuitBreakerOptions('google', {
          name: GeminiProviderAdapter.CIRCUIT_NAME,
        }),
      );
    } catch (error) {
      if (error instanceof CircuitOpenException) {
        throw new GeminiProviderError(
          'CIRCUIT_BREAKER_OPEN',
          'Circuit breaker is open - too many recent failures',
          true,
        );
      }
      if (error instanceof GeminiProviderError) {
        throw error;
      }
      throw this.mapError(error);
    }
  }

  /**
   * Executa a chamada ao Gemini SDK
   */
  private async executeCompletion(
    request: AICompletionRequest,
    model: string,
  ): Promise<AICompletionResponseDto> {
    const client = this.client;

    // Separar system instruction das mensagens
    const systemInstruction = this.extractSystemInstruction(request);
    const contents = this.convertMessages(request);

    const generativeModel = client.getGenerativeModel({
      model,
      ...(systemInstruction ? { systemInstruction } : {}),
    });

    const result = await generativeModel.generateContent({
      contents,
      generationConfig: {
        ...(request.temperature != null
          ? { temperature: request.temperature }
          : {}),
        ...(request.maxTokens != null
          ? { maxOutputTokens: request.maxTokens }
          : {}),
        ...(request.topP != null ? { topP: request.topP } : {}),
      },
    });

    return this.translator.translate(result, model);
  }

  /**
   * Extrai system instruction das mensagens (primeira mensagem com role=system)
   */
  private extractSystemInstruction(
    request: AICompletionRequest,
  ): string | undefined {
    const systemMessages = request.messages.filter((m) => m.role === 'system');
    if (!systemMessages.length) {
      return undefined;
    }
    return systemMessages.map((m) => m.content).join('\n');
  }

  /**
   * Converte mensagens do formato normalizado para o formato Google Content[]
   *
   * - user → user
   * - assistant → model
   * - system é excluído (vai para systemInstruction)
   */
  private convertMessages(request: AICompletionRequest): Content[] {
    return request.messages
      .filter((m) => m.role !== 'system')
      .map((m) => ({
        role: m.role === 'assistant' ? 'model' : 'user',
        parts: [{ text: m.content }],
      }));
  }

  /**
   * Mapeia erros do SDK para erros padronizados do gateway
   */
  private mapError(error: unknown): GeminiProviderError {
    if (!(error instanceof Error)) {
      return new GeminiProviderError(
        'PROVIDER_SERVER_ERROR',
        'Unknown Gemini error',
        false,
      );
    }

    const message = error.message ?? 'Gemini API error';

    // Erros de autenticação
    if (
      message.includes('API key') ||
      message.includes('401') ||
      message.includes('403')
    ) {
      return new GeminiProviderError(
        'PROVIDER_AUTH_ERROR',
        'Gemini authentication failed',
        false,
        error,
      );
    }

    // Rate limiting
    if (message.includes('429') || message.includes('Resource exhausted')) {
      return new GeminiProviderError(
        'PROVIDER_RATE_LIMIT',
        'Gemini rate limit exceeded',
        true,
        error,
      );
    }

    // Timeout
    if (message.includes('timeout') || message.includes('DEADLINE_EXCEEDED')) {
      return new GeminiProviderError(
        'PROVIDER_TIMEOUT',
        'Gemini request timed out',
        true,
        error,
      );
    }

    // Safety / content filter
    if (message.includes('SAFETY') || message.includes('blocked')) {
      return new GeminiProviderError(
        'PROVIDER_CONTENT_FILTER',
        'Content blocked by Gemini safety filters',
        false,
        error,
      );
    }

    // Erro genérico
    return new GeminiProviderError(
      'PROVIDER_SERVER_ERROR',
      'Gemini API request failed',
      true,
      error,
    );
  }
}
