import { Type } from 'class-transformer';
import {
  ArrayNotEmpty,
  IsArray,
  IsInt,
  IsNumber,
  IsOptional,
  IsString,
  Min,
  ValidateNested,
} from 'class-validator';
import { AIChatMessageDto } from './ai-chat-message.dto';

/**
 * Payload de requisição para completions de chat com AI.
 *
 * Encapsula o histórico de conversa e os parâmetros do provider utilizados
 * pelo endpoint interno `/ai/openai/chat`.
 */
export class AIChatRequestDto {
  @IsArray()
  @ArrayNotEmpty()
  @ValidateNested({ each: true })
  @Type(() => AIChatMessageDto)
  messages!: AIChatMessageDto[];

  @IsOptional()
  @IsString()
  model?: string;

  @IsOptional()
  @Type(() => Number)
  @IsNumber()
  temperature?: number;

  @IsOptional()
  @Type(() => Number)
  @IsInt()
  @Min(1)
  maxTokens?: number;
}
