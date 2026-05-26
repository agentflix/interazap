/**
 * Adapter do provider Google Gemini para o domínio de AI do gateway.
 *
 * Contexto: implementa a interface `AIProvider` usando o SDK oficial `@google/generative-ai`.
 * Suporta circuit breaker compartilhado e mapeia erros do SDK para `GeminiProviderError`.
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
 * Erro customizado do provider Gemini com código de erro padronizado do gateway.
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
   * Metadados do provider Gemini para discovery na factory.
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
   * Executa uma completion no Gemini e retorna resposta normalizada.
   * @param request - Requisição de completion normalizada.
   * @returns Resposta normalizada do provider Gemini.
   * @throws GeminiProviderError Quando a chave de API não está configurada ou ocorre erro na chamada.
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
   * Executa a chamada ao Gemini SDK com o modelo e configuração fornecidos.
   * @param request - Requisição de completion normalizada.
   * @param model - Nome do modelo a utilizar.
   * @returns Resposta normalizada após tradução pelo `GeminiTranslator`.
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
   * Extrai a instrução de sistema das mensagens com papel `system`.
   * @param request - Requisição de completion contendo o histórico de mensagens.
   * @returns Texto concatenado das mensagens de sistema, ou `undefined` quando ausente.
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
   * Converte mensagens do formato normalizado para o formato `Content[]` do SDK Google.
   *
   * Mapeamento: `user` → `user`, `assistant` → `model`, `system` → excluído (vai para `systemInstruction`).
   * @param request - Requisição com o histórico de mensagens a converter.
   * @returns Array no formato `Content[]` aceito pelo SDK Gemini.
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
   * Mapeia erros do SDK Gemini para instâncias de `GeminiProviderError` com código padronizado.
   * @param error - Erro capturado durante a chamada ao Gemini.
   * @returns Instância de `GeminiProviderError` com código e flag de retentativa.
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
