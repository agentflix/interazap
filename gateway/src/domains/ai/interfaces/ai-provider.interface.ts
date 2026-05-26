/**
 * Interface de provider de AI (porta do domínio).
 *
 * Contrato que todo adapter de IA deve implementar.
 * Segue o padrão Ports & Adapters para permitir múltiplos providers.
 */

import { AICompletionRequest } from './ai-completion-request.dto';
import { AICompletionResponseDto } from './ai-completion-response.dto';

/**
 * Contrato que todo adapter de IA deve implementar.
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
   * @param request - Requisição de completion com mensagens e configurações
   * @returns Resposta normalizada com conteúdo e métricas de tokens
   * @throws Error Em caso de falha (timeout, rate limit, etc.)
   */
  complete(request: AICompletionRequest): Promise<AICompletionResponseDto>;

  /**
   * Transmite a resposta em streaming retornando um `AsyncGenerator` de chunks de texto.
   *
   * Método opcional preparado para uso futuro. Quando implementado, emite chunks
   * de texto à medida que são gerados pelo modelo.
   *
   * @param request - Requisição de completion
   * @yields Chunks de texto da resposta
   */
  stream?(request: AICompletionRequest): AsyncGenerator<string, void, unknown>;

  /**
   * Verifica se o provider está disponível e saudável.
   *
   * @returns `true` quando o provider está pronto para receber requisições
   */
  isHealthy?(): Promise<boolean>;
}

/** Token de injeção para providers de AI no container NestJS. */
export const AI_PROVIDER_TOKEN = Symbol('AI_PROVIDER');

/** Mapa tipado que associa nomes de providers às suas instâncias. */
export type AIProviderMap = Map<string, AIProvider>;

/**
 * Metadados de registro de um provider de AI para uso na factory.
 */
export interface AIProviderMetadata {
  /** Nome único do provider. */
  name: string;
  /** Descrição textual das capacidades do provider. */
  description?: string;
  /** Lista de modelos suportados pelo provider. */
  supportedModels?: string[];
  /** Indica se o provider suporta streaming de respostas. */
  supportsStreaming?: boolean;
}
