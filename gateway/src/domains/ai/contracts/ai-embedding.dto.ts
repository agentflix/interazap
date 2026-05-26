import {
  IsString,
  IsArray,
  IsOptional,
  IsNumber,
  IsBoolean,
} from 'class-validator';

/**
 * Comando para solicitar a geração de embeddings ao domínio de AI.
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
 * Resultado de um único vetor de embedding gerado pelo provider.
 */
export class EmbeddingResultDto {
  @IsNumber()
  index!: number;

  @IsArray()
  @IsNumber({}, { each: true })
  embedding!: number[];
}

/**
 * Resultado completo de uma requisição de embeddings de AI.
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
