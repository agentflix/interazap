import { IsBoolean, IsOptional, IsString, Matches } from 'class-validator';

/**
 * Payload para envio de mensagem de texto via Uazapi.
 * Inclui campos opcionais de link preview, resposta e mencao.
 */
export class SendTextDto {
  @IsString()
  @Matches(/^[0-9@.\w-]{6,}$/)
  number!: string;

  @IsString()
  text!: string;

  @IsOptional()
  @IsBoolean()
  linkPreview?: boolean;

  @IsOptional()
  @IsString()
  linkPreviewTitle?: string;

  @IsOptional()
  @IsString()
  linkPreviewDescription?: string;

  @IsOptional()
  @IsString()
  linkPreviewImage?: string;

  @IsOptional()
  @IsBoolean()
  linkPreviewLarge?: boolean;

  @IsOptional()
  @IsString()
  replyid?: string;

  @IsOptional()
  @IsString()
  mentions?: string;
}
