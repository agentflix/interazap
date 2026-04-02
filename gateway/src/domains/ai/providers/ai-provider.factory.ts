/**
 * AI Provider Factory
 *
 * Factory que resolve o adapter correto por nome do provider.
 * Permite adicionar novos providers sem modificar consumers.
 */

import { Injectable, Logger } from '@nestjs/common';
import { AIProvider, AIProviderMap } from '../interfaces/ai-provider.interface';
import { OpenAIProviderAdapter } from './openai/openai-provider.adapter';
import { GeminiProviderAdapter } from './google/gemini-provider.adapter';
import { MiniMaxProviderAdapter } from './minimax/minimax-provider.adapter';
import {
  GatewayErrorCode,
  createGatewayError,
} from '../../../common/interfaces/gateway-response.interface';

/**
 * Erro quando provider não é encontrado
 */
export class UnknownProviderError extends Error {
  readonly code: GatewayErrorCode = 'UNKNOWN_PROVIDER';
  readonly retryable = false;

  constructor(providerName: string) {
    super(`Unknown AI provider: '${providerName}'`);
    this.name = 'UnknownProviderError';
  }

  toGatewayError() {
    return createGatewayError(this.code, this.message);
  }
}

@Injectable()
export class AIProviderFactory {
  private readonly logger = new Logger(AIProviderFactory.name);
  private readonly providers: AIProviderMap = new Map();

  constructor(
    private readonly openaiAdapter: OpenAIProviderAdapter,
    private readonly geminiAdapter: GeminiProviderAdapter,
    private readonly minimaxAdapter: MiniMaxProviderAdapter,
  ) {
    this.registerProviders();
  }

  /**
   * Registra todos os providers disponíveis
   */
  private registerProviders(): void {
    // Registra OpenAI
    this.registerProvider(this.openaiAdapter);

    // Registra Gemini
    this.registerProvider(this.geminiAdapter);

    // Registra MiniMax
    this.registerProvider(this.minimaxAdapter);

    // Log providers registrados
    const providerNames = Array.from(this.providers.keys());
    this.logger.log(`AI providers registered: ${providerNames.join(', ')}`);
  }

  /**
   * Registra um provider no factory
   */
  registerProvider(provider: AIProvider): void {
    const name = provider.name.toLowerCase();
    this.providers.set(name, provider);
    this.logger.debug(`Registered AI provider: ${name}`);
  }

  /**
   * Resolve provider por nome
   *
   * @param name - Nome do provider (case insensitive)
   * @returns AIProvider instance
   * @throws UnknownProviderError se provider não existe
   */
  getProvider(name: string): AIProvider {
    const normalizedName = name.toLowerCase();
    const provider = this.providers.get(normalizedName);

    if (!provider) {
      throw new UnknownProviderError(name);
    }

    return provider;
  }

  /**
   * Verifica se um provider existe
   */
  hasProvider(name: string): boolean {
    return this.providers.has(name.toLowerCase());
  }

  /**
   * Lista todos os providers disponíveis
   */
  listProviders(): string[] {
    return Array.from(this.providers.keys());
  }

  /**
   * Retorna o provider padrão (OpenAI)
   */
  getDefaultProvider(): AIProvider {
    return this.openaiAdapter;
  }

  /**
   * Verifica health de todos os providers
   */
  async checkHealth(): Promise<Map<string, boolean>> {
    const health = new Map<string, boolean>();

    for (const [name, provider] of this.providers) {
      try {
        const isHealthy = provider.isHealthy
          ? await provider.isHealthy()
          : true;
        health.set(name, isHealthy);
      } catch {
        health.set(name, false);
      }
    }

    return health;
  }
}
