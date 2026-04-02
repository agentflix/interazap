/**
 * AI Provider Interface (Port)
 *
 * Contrato que todo adapter de IA deve implementar.
 * Segue o padrão Ports & Adapters para permitir múltiplos providers.
 */

import { AICompletionRequest } from './ai-completion-request.dto';
import { AICompletionResponseDto } from './ai-completion-response.dto';

/**
 * Interface que todo provider de IA deve implementar
 *
 * Cada provider (OpenAI, Gemini, Anthropic, etc.) implementa esta interface,
 * permitindo que o consumer seja agnóstico ao provider específico.
 *
 * @example
 * ```typescript
 * class OpenAIAdapter implements AIProvider {
 *   readonly name = 'openai';
 *
 *   async complete(request: AICompletionRequest): Promise<AICompletionResponseDto> {
 *     // Implementação específica do OpenAI
 *   }
 * }
 * ```
 */
export interface AIProvider {
  /**
   * Nome único do provider.
   * Usado para resolução via factory.
   *
   * @example 'openai', 'gemini', 'anthropic', 'azure-openai'
   */
  readonly name: string;

  /**
   * Executa completion e retorna resposta normalizada.
   *
   * @param request - Request de completion com mensagens e configurações
   * @returns Resposta normalizada com conteúdo e métricas de tokens
   * @throws Error em caso de falha (timeout, rate limit, etc.)
   */
  complete(request: AICompletionRequest): Promise<AICompletionResponseDto>;

  /**
   * Streaming de resposta (opcional - preparação para fase 2).
   *
   * Quando implementado, retorna um AsyncGenerator que emite
   * chunks de texto à medida que são gerados.
   *
   * @param request - Request de completion
   * @yields Chunks de texto da resposta
   *
   * @note Este método é opcional e será implementado em fase futura.
   *       A interface já o inclui para preparar a arquitetura.
   */
  stream?(request: AICompletionRequest): AsyncGenerator<string, void, unknown>;

  /**
   * Verifica se o provider está disponível/saudável.
   *
   * @returns true se o provider está pronto para receber requests
   */
  isHealthy?(): Promise<boolean>;
}

/**
 * Token de injeção para providers
 */
export const AI_PROVIDER_TOKEN = Symbol('AI_PROVIDER');

/**
 * Tipo para map de providers
 */
export type AIProviderMap = Map<string, AIProvider>;

/**
 * Metadata para registro de provider
 */
export interface AIProviderMetadata {
  name: string;
  description?: string;
  supportedModels?: string[];
  supportsStreaming?: boolean;
}
