/**
 * OpenAI Translator (Anti-Corruption Layer)
 *
 * Traduz respostas do OpenAI SDK para o DTO normalizado.
 * Isola o domínio das especificidades da API do OpenAI.
 */

import { Injectable } from '@nestjs/common';
import type { ChatCompletion } from 'openai/resources/chat/completions';
import {
  AICompletionResponseDto,
  FinishReason,
  createAICompletionResponse,
} from '../../interfaces/ai-completion-response.dto';

/**
 * Finish reasons válidos do OpenAI
 */
const VALID_FINISH_REASONS = new Set([
  'stop',
  'length',
  'content_filter',
  'tool_calls',
  'function_call',
]);

@Injectable()
export class OpenAITranslator {
  /**
   * Traduz ChatCompletion do OpenAI para AICompletionResponseDto normalizado
   *
   * @param response - Resposta do OpenAI SDK
   * @returns DTO normalizado
   */
  translate(response: ChatCompletion): AICompletionResponseDto {
    const choice = response.choices?.[0];
    const usage = response.usage;

    return createAICompletionResponse({
      content: this.extractContent(choice),
      promptTokens: usage?.prompt_tokens ?? 0,
      completionTokens: usage?.completion_tokens ?? 0,
      totalTokens: usage?.total_tokens ?? 0,
      model: response.model ?? 'unknown',
      finishReason: this.normalizeFinishReason(choice?.finish_reason),
    });
  }

  /**
   * Extrai o conteúdo da resposta
   */
  private extractContent(
    choice: ChatCompletion['choices'][0] | undefined,
  ): string {
    if (!choice) {
      return '';
    }

    // Prioriza content textual
    if (choice.message?.content) {
      return choice.message.content;
    }

    // Se for tool_calls, serializa para JSON
    if (choice.message?.tool_calls?.length) {
      return JSON.stringify(choice.message.tool_calls);
    }

    // Fallback para function_call (deprecated mas ainda suportado)
    if (choice.message?.function_call) {
      return JSON.stringify(choice.message.function_call);
    }

    return '';
  }

  /**
   * Normaliza finish_reason do OpenAI para nosso tipo
   *
   * Mapeamento:
   * - "stop" → "stop"
   * - "length" → "length"
   * - "content_filter" → "content_filter"
   * - "tool_calls" → "tool_calls"
   * - "function_call" → "tool_calls" (mapeado para novo padrão)
   * - null/undefined/outro → null
   */
  private normalizeFinishReason(
    reason: string | null | undefined,
  ): FinishReason {
    if (!reason) {
      return null;
    }

    // Mapeia function_call para tool_calls (padrão novo)
    if (reason === 'function_call') {
      return 'tool_calls';
    }

    // Verifica se é um reason válido
    if (VALID_FINISH_REASONS.has(reason)) {
      return reason as FinishReason;
    }

    // Reason desconhecido
    return null;
  }

  /**
   * Traduz resposta de streaming (para uso futuro)
   */
  translateStreamChunk(chunk: unknown): string {
    // Null/undefined check
    if (chunk === null || chunk === undefined) {
      return '';
    }

    // Type guard para chunk de streaming
    const typedChunk = chunk as {
      choices?: Array<{ delta?: { content?: string } }>;
    };

    return typedChunk?.choices?.[0]?.delta?.content ?? '';
  }
}
