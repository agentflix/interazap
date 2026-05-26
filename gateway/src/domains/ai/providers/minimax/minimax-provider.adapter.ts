/**
 * Adapter do provider MiniMax para o domínio de AI do gateway.
 *
 * Contexto: implementa a interface `AIProvider` usando a API HTTP compatível com OpenAI.
 * Suporta circuit breaker compartilhado e mapeia erros HTTP para `MiniMaxProviderError`.
 */

import { Injectable, Logger } from '@nestjs/common';
import {
  AIProvider,
  AIProviderMetadata,
} from '../../interfaces/ai-provider.interface';
import { AICompletionRequest } from '../../interfaces/ai-completion-request.dto';
import { AICompletionResponseDto } from '../../interfaces/ai-completion-response.dto';
import { MiniMaxConfigService } from './minimax.config';
import { MiniMaxTranslator, MiniMaxChatResponse } from './minimax.translator';
import { GatewayErrorCode } from '../../../../common/interfaces/gateway-response.interface';
import {
  CircuitBreakerService,
  CircuitOpenException,
} from '../../../../shared/services/circuit-breaker';
import { getCircuitBreakerOptions } from '../../../../core/config/circuit-breaker.config';

/**
 * Erro customizado do provider MiniMax com código de erro padronizado do gateway.
 */
export class MiniMaxProviderError extends Error {
  constructor(
    public readonly code: GatewayErrorCode,
    message: string,
    public readonly retryable: boolean = false,
    public readonly originalError?: Error,
  ) {
    super(message);
    this.name = 'MiniMaxProviderError';
  }
}

@Injectable()
export class MiniMaxProviderAdapter implements AIProvider {
  private static readonly CIRCUIT_NAME = 'minimax-provider';
  readonly name = 'minimax';

  private readonly logger = new Logger(MiniMaxProviderAdapter.name);

  /**
   * Metadados do provider MiniMax para discovery na factory.
   */
  static readonly metadata: AIProviderMetadata = {
    name: 'minimax',
    description: 'MiniMax models (M2.5, M2.5-highspeed)',
    supportedModels: ['MiniMax-M2.5', 'MiniMax-M2.5-highspeed'],
    supportsStreaming: false,
  };

  constructor(
    private readonly config: MiniMaxConfigService,
    private readonly translator: MiniMaxTranslator,
    private readonly circuitBreaker: CircuitBreakerService,
  ) {}

  /**
   * Executa completion e retorna resposta normalizada
   *
   * @param request - Requisição de completion normalizada
   * @returns Resposta normalizada do provider
   * @throws MiniMaxProviderError em caso de falha
   */
  async complete(
    request: AICompletionRequest,
  ): Promise<AICompletionResponseDto> {
    if (!this.config.isConfigured()) {
      throw new MiniMaxProviderError(
        'PROVIDER_AUTH_ERROR',
        'MINIMAX_API_KEY not configured',
        false,
      );
    }

    const model = request.model ?? this.config.getDefaultModel();

    try {
      return await this.circuitBreaker.call(
        MiniMaxProviderAdapter.CIRCUIT_NAME,
        () => this.executeCompletion(request, model),
        getCircuitBreakerOptions('minimax', {
          name: MiniMaxProviderAdapter.CIRCUIT_NAME,
        }),
      );
    } catch (error) {
      if (error instanceof CircuitOpenException) {
        throw new MiniMaxProviderError(
          'CIRCUIT_BREAKER_OPEN',
          'Circuit breaker is open - too many recent failures',
          true,
        );
      }
      if (error instanceof MiniMaxProviderError) {
        throw error;
      }
      throw this.mapError(error);
    }
  }

  /**
   * Executa a chamada HTTP para a API MiniMax e retorna resposta normalizada.
   * @param request - Requisição de completion normalizada.
   * @param model - Nome do modelo a utilizar.
   * @returns Resposta normalizada após tradução pelo `MiniMaxTranslator`.
   * @throws MiniMaxProviderError Quando a resposta HTTP indica erro ou ocorre timeout.
   */
  private async executeCompletion(
    request: AICompletionRequest,
    model: string,
  ): Promise<AICompletionResponseDto> {
    const url = `${this.config.getBaseUrl()}/v1/chat/completions`;

    const body: Record<string, unknown> = {
      model,
      messages: request.messages.map((m) => ({
        role: m.role,
        content: m.content,
      })),
      stream: false,
    };

    if (request.temperature != null) body.temperature = request.temperature;
    if (request.maxTokens != null) body.max_tokens = request.maxTokens;
    if (request.topP != null) body.top_p = request.topP;

    const controller = new AbortController();
    const timeoutId = setTimeout(
      () => controller.abort(),
      this.config.getTimeoutMs(),
    );

    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${this.config.getApiKey()}`,
        },
        body: JSON.stringify(body),
        signal: controller.signal,
      });

      if (!response.ok) {
        const errorText = await response.text().catch(() => 'Unknown error');
        throw this.mapHttpError(response.status, errorText);
      }

      const data = (await response.json()) as MiniMaxChatResponse;
      return this.translator.translate(data, model);
    } finally {
      clearTimeout(timeoutId);
    }
  }

  /**
   * Mapeia erros HTTP para instâncias de `MiniMaxProviderError` com código padronizado.
   * @param status - Código de status HTTP recebido na resposta.
   * @param body - Corpo da resposta de erro.
   * @returns Instância de `MiniMaxProviderError` com código e flag de retentativa.
   */
  private mapHttpError(status: number, body: string): MiniMaxProviderError {
    if (status === 401 || status === 403) {
      return new MiniMaxProviderError(
        'PROVIDER_AUTH_ERROR',
        `MiniMax authentication failed: ${status}`,
        false,
      );
    }
    if (status === 429) {
      return new MiniMaxProviderError(
        'PROVIDER_RATE_LIMIT',
        'MiniMax rate limit exceeded',
        true,
      );
    }
    if (status >= 500) {
      return new MiniMaxProviderError(
        'PROVIDER_SERVER_ERROR',
        `MiniMax server error: ${status} - ${body}`,
        true,
      );
    }
    return new MiniMaxProviderError(
      'PROVIDER_SERVER_ERROR',
      `MiniMax request failed: ${status} - ${body}`,
      false,
    );
  }

  /**
   * Mapeia erros genéricos (como `AbortError`) para instâncias de `MiniMaxProviderError`.
   * @param error - Erro capturado durante a chamada HTTP ao MiniMax.
   * @returns Instância de `MiniMaxProviderError` com código e flag de retentativa.
   */
  private mapError(error: unknown): MiniMaxProviderError {
    if (!(error instanceof Error)) {
      return new MiniMaxProviderError(
        'PROVIDER_SERVER_ERROR',
        'Unknown MiniMax error',
        false,
      );
    }

    if (error.name === 'AbortError') {
      return new MiniMaxProviderError(
        'PROVIDER_TIMEOUT',
        'MiniMax request timed out',
        true,
        error,
      );
    }

    return new MiniMaxProviderError(
      'PROVIDER_SERVER_ERROR',
      `MiniMax API request failed: ${error.message}`,
      true,
      error,
    );
  }
}
