/**
 * DTO de requisição de completion para AI.
 *
 * Estrutura normalizada para requisições de completion vindas do Laravel via Redis Streams,
 * independente do provider utilizado (OpenAI, Gemini, etc.).
 */

import {
  IsString,
  IsOptional,
  IsArray,
  ValidateNested,
  IsNumber,
  IsBoolean,
  Min,
  Max,
  IsIn,
} from 'class-validator';
import { Type } from 'class-transformer';
import type { ChatMessageRole } from '../models/ai-completion.model';

export type {
  AICompletionRequest,
  ChatMessageRole,
} from '../models/ai-completion.model';

/**
 * Representa uma mensagem individual no histórico do chat enviado ao provider.
 */
export class ChatMessageDto {
  /** Papel da mensagem no contexto do chat (`system`, `user` ou `assistant`). */
  @IsString()
  @IsIn(['system', 'user', 'assistant'])
  role!: ChatMessageRole;

  /** Conteúdo textual da mensagem. */
  @IsString()
  content!: string;

  /** Nome opcional do participante (útil para conversas com múltiplos usuários). */
  @IsOptional()
  @IsString()
  name?: string;
}

/**
 * DTO para requisição de AI completion recebida pelo gateway.
 *
 * Representa o payload dentro de um `GatewayMessage` com `domain: 'ai'` e `action: 'completion'`.
 */
export class AICompletionRequestDto {
  /** Histórico de mensagens do chat incluindo mensagens de sistema e conversa. */
  @IsArray()
  @ValidateNested({ each: true })
  @Type(() => ChatMessageDto)
  messages!: ChatMessageDto[];

  /**
   * Modelo a usar para completion.
   * Se não informado, usa o default do provider.
   * @example "gpt-4o", "gpt-4o-mini", "gpt-3.5-turbo"
   */
  @IsOptional()
  @IsString()
  model?: string;

  /**
   * Máximo de tokens na resposta gerada.
   * @default Definido pelo provider (geralmente 4096)
   */
  @IsOptional()
  @IsNumber()
  @Min(1)
  @Max(128000)
  maxTokens?: number;

  /**
   * Temperatura para controle de aleatoriedade.
   * 0 = determinístico, 2 = muito criativo.
   * @default 1.0
   */
  @IsOptional()
  @IsNumber()
  @Min(0)
  @Max(2)
  temperature?: number;

  /**
   * Se deve fazer streaming da resposta.
   * @default false
   */
  @IsOptional()
  @IsBoolean()
  stream?: boolean;

  /**
   * Top-p (nucleus sampling) - alternativa à temperature.
   * @default 1.0
   */
  @IsOptional()
  @IsNumber()
  @Min(0)
  @Max(1)
  topP?: number;

  /**
   * Penalidade por frequência de tokens.
   * @default 0
   */
  @IsOptional()
  @IsNumber()
  @Min(-2)
  @Max(2)
  frequencyPenalty?: number;

  /**
   * Penalidade por presença de tokens.
   * @default 0
   */
  @IsOptional()
  @IsNumber()
  @Min(-2)
  @Max(2)
  presencePenalty?: number;
}
