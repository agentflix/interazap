import { IsBoolean, IsOptional, IsString, Matches } from 'class-validator';

/**
 * Payload for sending a text message via Uazapi.
 * Includes optional link preview fields and reply/mention support.
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
