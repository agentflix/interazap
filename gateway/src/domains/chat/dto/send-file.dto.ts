import {
  IsBoolean,
  IsNotEmpty,
  IsNumber,
  IsOptional,
  IsString,
} from 'class-validator';

/**
 * Payload para envio de arquivo ou midia via Uazapi.
 * Aceita `url` ou `file` (base64) como fonte do conteudo.
 */
export class SendFileDto {
  @IsString()
  @IsNotEmpty()
  number!: string;

  @IsString()
  @IsOptional()
  url?: string;

  @IsString()
  @IsOptional()
  file?: string;

  @IsString()
  @IsOptional()
  type?: string;

  @IsString()
  @IsOptional()
  caption?: string;

  @IsString()
  @IsOptional()
  text?: string;

  @IsString()
  @IsOptional()
  docName?: string;

  @IsString()
  @IsOptional()
  fileName?: string;

  @IsString()
  @IsOptional()
  mimetype?: string;

  @IsString()
  @IsOptional()
  replyid?: string;

  @IsString()
  @IsOptional()
  thumbnail?: string;

  @IsString()
  @IsOptional()
  mentions?: string;

  @IsOptional()
  @IsBoolean()
  readchat?: boolean;

  @IsOptional()
  @IsBoolean()
  readmessages?: boolean;

  @IsOptional()
  @IsNumber()
  delay?: number;

  @IsOptional()
  @IsBoolean()
  forward?: boolean;

  @IsString()
  @IsOptional()
  track_source?: string;

  @IsString()
  @IsOptional()
  track_id?: string;

  @IsOptional()
  @IsBoolean()
  async?: boolean;
}
