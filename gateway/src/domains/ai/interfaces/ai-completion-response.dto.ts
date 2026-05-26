/**
 * DTO de resposta normalizada de completion para AI.
 *
 * Estrutura unificada para respostas de qualquer provider de IA, abstraindo
 * as diferenças entre OpenAI, Gemini, MiniMax etc.
 */
import type {
  AICompletionResponseDto,
  TokenUsage,
} from '../models/ai-completion.model';

export type {
  AICompletionResponseDto,
  FinishReason,
  TokenUsage,
} from '../models/ai-completion.model';

/**
 * Cria um `AICompletionResponseDto` preenchendo valores ausentes com defaults seguros.
 *
 * @param partial - Campos parciais da resposta com `content` e `model` obrigatórios.
 * @returns Resposta normalizada com todos os campos de token preenchidos.
 */
export function createAICompletionResponse(
  partial: Partial<AICompletionResponseDto> &
    Pick<AICompletionResponseDto, 'content' | 'model'>,
): AICompletionResponseDto {
  return {
    content: partial.content,
    promptTokens: partial.promptTokens ?? 0,
    completionTokens: partial.completionTokens ?? 0,
    totalTokens: partial.totalTokens ?? 0,
    model: partial.model,
    finishReason: partial.finishReason ?? null,
  };
}

/**
 * Extrai o resumo de uso de tokens de um `AICompletionResponseDto`.
 *
 * @param response - Resposta normalizada da qual extrair os tokens.
 * @returns Objeto `TokenUsage` com os contadores de tokens da completion.
 */
export function extractTokenUsage(
  response: AICompletionResponseDto,
): TokenUsage {
  return {
    promptTokens: response.promptTokens,
    completionTokens: response.completionTokens,
    totalTokens: response.totalTokens,
  };
}
