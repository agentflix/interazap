import { IsBoolean, IsOptional, IsString } from 'class-validator';

/**
 * Payload para download de midia recebida via Uazapi.
 * O ID da midia e obrigatorio; os flags de formato de saida sao opcionais.
 */
export class DownloadMediaDto {
  @IsString()
  id!: string;

  @IsOptional()
  @IsBoolean()
  return_link?: boolean;

  @IsOptional()
  @IsBoolean()
  return_base64?: boolean;

  @IsOptional()
  @IsBoolean()
  generate_mp3?: boolean;

  @IsOptional()
  @IsBoolean()
  transcribe?: boolean;

  @IsOptional()
  @IsString()
  openai_apikey?: string | null;

  @IsOptional()
  @IsBoolean()
  download_quoted?: boolean;
}
