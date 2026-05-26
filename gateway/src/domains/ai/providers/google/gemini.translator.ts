/**
 * Tradutor responsável por converter respostas do SDK Google Gemini para o DTO normalizado.
 *
 * Contexto: camada anti-corrupção que isola o domínio de AI das especificidades
 * do SDK `@google/generative-ai`, normalizando conteúdo, tokens e finish reason.
 */

import { Injectable } from '@nestjs/common';
import type { GenerateContentResult } from '@google/generative-ai';
import {
  AICompletionResponseDto,
  FinishReason,
  createAICompletionResponse,
} from '../../interfaces/ai-completion-response.dto';

/**
 * Mapeamento dos finish reasons do Gemini para o formato normalizado do domínio.
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
   * Traduz o `GenerateContentResult` do Gemini para `AICompletionResponseDto` normalizado.
   * @param response - Resposta retornada pelo SDK Gemini.
   * @param model - Nome do modelo utilizado na requisição.
   * @returns DTO normalizado com conteúdo, tokens e finish reason.
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
   * Extrai o conteúdo textual da resposta Gemini, com fallback para candidates.
   * @param response - Resposta do SDK Gemini.
   * @returns Texto extraído ou string vazia quando não disponível.
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
   * Normaliza o finish reason do Gemini para o tipo interno do domínio.
   *
   * Mapeamento: `STOP` → `stop`, `MAX_TOKENS` → `length`, `SAFETY`/`RECITATION` → `content_filter`, outros → `stop`.
   * @param response - Resposta do SDK Gemini contendo candidates.
   * @returns Finish reason normalizado ou `null` quando não disponível.
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
