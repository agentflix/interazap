import {
  IsString,
  IsOptional,
  IsArray,
  ValidateNested,
  IsNumber,
  IsObject,
  IsBoolean,
} from 'class-validator';
import { Type } from 'class-transformer';

/**
 * Chat Message for OpenAI API
 */
export class ChatMessageDto {
  @IsString()
  role!: 'system' | 'user' | 'assistant' | 'function';

  @IsString()
  content!: string;

  @IsOptional()
  @IsString()
  name?: string;
}

/**
 * Command to request AI chat completion
 */
export class AiChatCommandDto {
  @IsString()
  tenantId!: string;

  @IsString()
  correlationId!: string;

  @IsArray()
  @ValidateNested({ each: true })
  @Type(() => ChatMessageDto)
  messages!: ChatMessageDto[];

  @IsOptional()
  @IsString()
  model?: string;

  @IsOptional()
  @IsNumber()
  temperature?: number;

  @IsOptional()
  @IsNumber()
  maxTokens?: number;

  @IsOptional()
  @IsObject()
  metadata?: Record<string, unknown>;
}

/**
 * Result of AI chat completion
 */
export class AiChatResultDto {
  @IsString()
  correlationId!: string;

  @IsString()
  tenantId!: string;

  @IsBoolean()
  success!: boolean;

  @IsOptional()
  @IsString()
  content?: string;

  @IsOptional()
  @IsString()
  finishReason?: string;

  @IsOptional()
  @IsNumber()
  promptTokens?: number;

  @IsOptional()
  @IsNumber()
  completionTokens?: number;

  @IsOptional()
  @IsNumber()
  totalTokens?: number;

  @IsOptional()
  @IsNumber()
  processingTimeMs?: number;

  @IsOptional()
  @IsString()
  error?: string;

  @IsOptional()
  @IsString()
  model?: string;
}
