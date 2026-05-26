/**
 * Factory responsável por resolver o adapter correto por nome de provider de AI.
 *
 * Contexto: registra automaticamente os adapters OpenAI, Gemini e MiniMax na inicialização
 * e expõe métodos para resolução por nome (case-insensitive) e verificação de saúde.
 * Permite adicionar novos providers sem modificar os consumers.
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
 * Erro lançado quando o provider solicitado não está registrado na factory.
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
   * Registra todos os providers disponíveis na inicialização da factory.
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
   * Registra um provider de AI na factory pelo seu nome normalizado.
   * @param provider - Instância do provider a ser registrada.
   */
  registerProvider(provider: AIProvider): void {
    const name = provider.name.toLowerCase();
    this.providers.set(name, provider);
    this.logger.debug(`Registered AI provider: ${name}`);
  }

  /**
   * Resolve o provider pelo nome informado, sem diferenciação de maiúsculas.
   * @param name - Nome do provider a ser resolvido (case insensitive).
   * @returns Instância do provider registrado.
   * @throws UnknownProviderError Quando o provider não está registrado na factory.
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
   * Verifica se um provider está registrado na factory.
   * @param name - Nome do provider a verificar.
   * @returns `true` quando o provider estiver registrado.
   */
  hasProvider(name: string): boolean {
    return this.providers.has(name.toLowerCase());
  }

  /**
   * Lista os nomes de todos os providers registrados.
   * @returns Array com os nomes normalizados dos providers disponíveis.
   */
  listProviders(): string[] {
    return Array.from(this.providers.keys());
  }

  /**
   * Retorna o provider padrão da plataforma (OpenAI).
   * @returns Instância do adapter OpenAI.
   */
  getDefaultProvider(): AIProvider {
    return this.openaiAdapter;
  }

  /**
   * Verifica a saúde de todos os providers registrados em paralelo.
   * @returns Mapa com o nome do provider e seu status de saúde.
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
