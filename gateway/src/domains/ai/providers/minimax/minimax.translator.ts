/**
 * Tradutor responsável por converter respostas da API MiniMax para o DTO normalizado.
 *
 * Contexto: camada anti-corrupção que isola o domínio de AI do formato de resposta
 * OpenAI-compatible da API MiniMax, normalizando conteúdo, tokens e finish reason.
 */

import { Injectable } from '@nestjs/common';
import {
  AICompletionResponseDto,
  FinishReason,
  createAICompletionResponse,
} from '../../interfaces/ai-completion-response.dto';

/**
 * Formato da resposta retornada pela API MiniMax, compatível com o padrão OpenAI.
 */
export interface MiniMaxChatResponse {
  id: string;
  object: string;
  created: number;
  model: string;
  choices: Array<{
    index: number;
    message: {
      role: string;
      content: string;
    };
    finish_reason: string;
  }>;
  usage?: {
    prompt_tokens?: number;
    completion_tokens?: number;
    total_tokens?: number;
  };
}

const FINISH_REASON_MAP: Record<string, FinishReason> = {
  stop: 'stop',
  length: 'length',
  content_filter: 'content_filter',
};

@Injectable()
export class MiniMaxTranslator {
  /**
   * Traduz a resposta bruta da API MiniMax para `AICompletionResponseDto` normalizado.
   * @param response - Resposta bruta retornada pela API MiniMax.
   * @param model - Nome do modelo utilizado na requisição.
   * @returns DTO normalizado com conteúdo, tokens e finish reason.
   */
  translate(
    response: MiniMaxChatResponse,
    model: string,
  ): AICompletionResponseDto {
    const content = this.extractContent(response);
    const usage = response.usage;

    return createAICompletionResponse({
      content,
      promptTokens: usage?.prompt_tokens ?? 0,
      completionTokens: usage?.completion_tokens ?? 0,
      totalTokens: usage?.total_tokens ?? 0,
      model,
      finishReason: this.normalizeFinishReason(response),
    });
  }

  /**
   * Extrai o conteúdo textual da primeira choice da resposta MiniMax.
   * @param response - Resposta bruta da API.
   * @returns Texto extraído ou string vazia quando não disponível.
   */
  private extractContent(response: MiniMaxChatResponse): string {
    return response.choices?.[0]?.message?.content ?? '';
  }

  /**
   * Normaliza o finish reason da resposta MiniMax para o tipo interno do domínio.
   * @param response - Resposta bruta da API.
   * @returns Finish reason normalizado ou `null` quando não disponível.
   */
  private normalizeFinishReason(response: MiniMaxChatResponse): FinishReason {
    const reason = response.choices?.[0]?.finish_reason;
    if (!reason) return null;
    return FINISH_REASON_MAP[reason] ?? 'stop';
  }
}
