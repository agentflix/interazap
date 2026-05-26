import { IsArray, IsOptional, IsString, ValidateNested } from 'class-validator';
import { Type } from 'class-transformer';

/**
 * Metadados do webhook Meta contendo identificadores do numero de telefone.
 */
export class MetaWebhookMetadataDto {
  @IsString()
  phone_number_id!: string;

  @IsString()
  @IsOptional()
  display_phone_number?: string;
}

/**
 * Valor do evento de mudanca do webhook Meta com metadados e produto de mensageria.
 */
export class MetaWebhookValueDto {
  @ValidateNested()
  @Type(() => MetaWebhookMetadataDto)
  @IsOptional()
  metadata?: MetaWebhookMetadataDto;

  @IsString()
  @IsOptional()
  messaging_product?: string;
}

/**
 * Entrada de mudanca do webhook Meta contendo o valor e o campo alterado.
 */
export class MetaWebhookChangeDto {
  @ValidateNested()
  @Type(() => MetaWebhookValueDto)
  @IsOptional()
  value?: MetaWebhookValueDto;

  @IsString()
  @IsOptional()
  field?: string;
}

/**
 * Entrada do webhook Meta agrupando as mudancas por conta WABA.
 */
export class MetaWebhookEntryDto {
  @IsArray()
  @ValidateNested({ each: true })
  @Type(() => MetaWebhookChangeDto)
  @IsOptional()
  changes?: MetaWebhookChangeDto[];

  @IsString()
  @IsOptional()
  id?: string;
}

/**
 * DTO raiz do payload de webhook da Meta WhatsApp Business API.
 * Usado para validacao de entrada no MetaWebhookController.
 */
export class MetaWebhookDto {
  @IsString()
  object!: string;

  @IsArray()
  @ValidateNested({ each: true })
  @Type(() => MetaWebhookEntryDto)
  entry!: MetaWebhookEntryDto[];
}
