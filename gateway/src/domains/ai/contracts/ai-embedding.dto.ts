import {
  IsString,
  IsArray,
  IsOptional,
  IsNumber,
  IsBoolean,
} from 'class-validator';

/**
 * Command to request AI embeddings
 */
export class AiEmbeddingCommandDto {
  @IsString()
  tenantId!: string;

  @IsString()
  correlationId!: string;

  @IsArray()
  @IsString({ each: true })
  texts!: string[];

  @IsOptional()
  @IsString()
  model?: string;
}

/**
 * Single embedding result
 */
export class EmbeddingResultDto {
  @IsNumber()
  index!: number;

  @IsArray()
  @IsNumber({}, { each: true })
  embedding!: number[];
}

/**
 * Result of AI embedding request
 */
export class AiEmbeddingResultDto {
  @IsString()
  correlationId!: string;

  @IsString()
  tenantId!: string;

  @IsBoolean()
  success!: boolean;

  @IsOptional()
  @IsArray()
  embeddings?: EmbeddingResultDto[];

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
