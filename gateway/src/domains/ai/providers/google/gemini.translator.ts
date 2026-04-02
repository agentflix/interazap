/**
 * Gemini Translator (Anti-Corruption Layer)
 *
 * Traduz respostas do Google Gemini SDK para o DTO normalizado.
 * Isola o domínio das especificidades da API do Gemini.
 */

import { Injectable } from '@nestjs/common';
import type { GenerateContentResult } from '@google/generative-ai';
import {
  AICompletionResponseDto,
  FinishReason,
  createAICompletionResponse,
} from '../../interfaces/ai-completion-response.dto';

/**
 * Mapeamento de finish reasons do Gemini para o formato normalizado
 */
const GEMINI_FINISH_REASON_MAP: Record<string, FinishReason> = {
  STOP: 'stop',
  MAX_TOKENS: 'length',
  SAFETY: 'content_filter',
  RECITATION: 'content_filter',
};

@Injectable()
export class GeminiTranslator {
  /**
   * Traduz GenerateContentResult do Gemini para AICompletionResponseDto normalizado
   *
   * @param response - Resposta do Gemini SDK
   * @param model - Nome do modelo usado na requisição
   * @returns DTO normalizado
   */
  translate(
    response: GenerateContentResult,
    model: string,
  ): AICompletionResponseDto {
    const content = this.extractContent(response);
    const usage = response.response?.usageMetadata;

    return createAICompletionResponse({
      content,
      promptTokens: usage?.promptTokenCount ?? 0,
      completionTokens: usage?.candidatesTokenCount ?? 0,
      totalTokens: usage?.totalTokenCount ?? 0,
      model,
      finishReason: this.normalizeFinishReason(response),
    });
  }

  /**
   * Extrai o conteúdo textual da resposta
   */
  private extractContent(response: GenerateContentResult): string {
    try {
      const text = response.response?.text?.();
      if (text) {
        return text;
      }
    } catch {
      // text() pode lançar se não houver conteúdo válido
    }

    // Fallback: tentar extrair dos candidates
    const candidates = response.response?.candidates;
    if (candidates?.length) {
      const parts = candidates[0]?.content?.parts;
      if (parts?.length) {
        return parts
          .map((part) => part.text ?? '')
          .filter(Boolean)
          .join('');
      }
    }

    return '';
  }

  /**
   * Normaliza finish_reason do Gemini para nosso tipo
   *
   * Mapeamento:
   * - "STOP" → "stop"
   * - "MAX_TOKENS" → "length"
   * - "SAFETY" → "content_filter"
   * - "RECITATION" → "content_filter"
   * - outros → "stop"
   */
  private normalizeFinishReason(response: GenerateContentResult): FinishReason {
    const candidates = response.response?.candidates;
    if (!candidates?.length) {
      return null;
    }

    const reason = candidates[0]?.finishReason;
    if (!reason) {
      return null;
    }

    return GEMINI_FINISH_REASON_MAP[reason] ?? 'stop';
  }
}
