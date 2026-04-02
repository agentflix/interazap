/**
 * MiniMax Translator (Anti-Corruption Layer)
 *
 * Traduz respostas da API MiniMax (OpenAI-compatible) para o DTO normalizado.
 */

import { Injectable } from '@nestjs/common';
import {
  AICompletionResponseDto,
  FinishReason,
  createAICompletionResponse,
} from '../../interfaces/ai-completion-response.dto';

/** Shape da resposta MiniMax (OpenAI-compatible) */
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
   * Traduz resposta MiniMax para DTO normalizado
   *
   * @param response - Resposta bruta da API MiniMax
   * @param model - Nome do modelo utilizado
   * @returns Resposta normalizada no formato AICompletionResponseDto
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
   * Extrai conteúdo textual da primeira choice
   */
  private extractContent(response: MiniMaxChatResponse): string {
    return response.choices?.[0]?.message?.content ?? '';
  }

  /**
   * Normaliza finish_reason para enum interno
   */
  private normalizeFinishReason(response: MiniMaxChatResponse): FinishReason {
    const reason = response.choices?.[0]?.finish_reason;
    if (!reason) return null;
    return FINISH_REASON_MAP[reason] ?? 'stop';
  }
}
