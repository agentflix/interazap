import { IsArray, IsOptional, IsString, ValidateNested } from 'class-validator';
import { Type } from 'class-transformer';

export class MetaWebhookMetadataDto {
  @IsString()
  phone_number_id!: string;

  @IsString()
  @IsOptional()
  display_phone_number?: string;
}

export class MetaWebhookValueDto {
  @ValidateNested()
  @Type(() => MetaWebhookMetadataDto)
  @IsOptional()
  metadata?: MetaWebhookMetadataDto;

  @IsString()
  @IsOptional()
  messaging_product?: string;
}

export class MetaWebhookChangeDto {
  @ValidateNested()
  @Type(() => MetaWebhookValueDto)
  @IsOptional()
  value?: MetaWebhookValueDto;

  @IsString()
  @IsOptional()
  field?: string;
}

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

export class MetaWebhookDto {
  @IsString()
  object!: string;

  @IsArray()
  @ValidateNested({ each: true })
  @Type(() => MetaWebhookEntryDto)
  entry!: MetaWebhookEntryDto[];
}
