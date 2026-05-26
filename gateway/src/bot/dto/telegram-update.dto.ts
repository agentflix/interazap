import {
  IsInt,
  IsString,
  IsBoolean,
  IsOptional,
  IsEnum,
  IsNumber,
  IsArray,
  ValidateNested,
  Min,
  MaxLength,
} from 'class-validator';
import { Type } from 'class-transformer';

// ─── TelegramUserDto ────────────────────────────────────────────────────────

/**
 * Representa um usuário ou bot no Telegram.
 *
 * Contexto: módulo bot, DTOs de entrada do webhook.
 */
export class TelegramUserDto {
  @IsInt()
  id: number;

  @IsBoolean()
  is_bot: boolean;

  @IsString()
  first_name: string;

  @IsOptional()
  @IsString()
  last_name?: string;

  @IsOptional()
  @IsString()
  username?: string;

  @IsOptional()
  @IsString()
  language_code?: string;
}

// ─── TelegramChatDto ────────────────────────────────────────────────────────

/**
 * Representa um chat no Telegram (privado, grupo, supergrupo ou canal).
 *
 * Contexto: módulo bot, DTOs de entrada do webhook.
 */
export class TelegramChatDto {
  @IsInt()
  id: number;

  @IsEnum(['private', 'group', 'supergroup', 'channel'])
  type: 'private' | 'group' | 'supergroup' | 'channel';

  @IsOptional()
  @IsString()
  title?: string;

  @IsOptional()
  @IsString()
  username?: string;

  @IsOptional()
  @IsString()
  first_name?: string;

  @IsOptional()
  @IsString()
  last_name?: string;
}

// ─── TelegramPhotoSizeDto ───────────────────────────────────────────────────

/**
 * Representa um tamanho de foto disponível no Telegram.
 *
 * Contexto: módulo bot, DTOs de entrada do webhook.
 */
export class TelegramPhotoSizeDto {
  @IsString()
  file_id: string;

  @IsString()
  file_unique_id: string;

  @IsInt()
  @Min(0)
  width: number;

  @IsInt()
  @Min(0)
  height: number;

  @IsOptional()
  @IsInt()
  file_size?: number;
}

// ─── TelegramVideoDto ───────────────────────────────────────────────────────

/**
 * Representa um arquivo de vídeo recebido via Telegram.
 *
 * Contexto: módulo bot, DTOs de entrada do webhook.
 */
export class TelegramVideoDto {
  @IsString()
  file_id: string;

  @IsString()
  file_unique_id: string;

  @IsInt()
  @Min(0)
  width: number;

  @IsInt()
  @Min(0)
  height: number;

  @IsInt()
  @Min(0)
  duration: number;

  @IsOptional()
  @IsString()
  mime_type?: string;

  @IsOptional()
  @IsInt()
  file_size?: number;

  @IsOptional()
  @ValidateNested()
  @Type(() => TelegramPhotoSizeDto)
  thumbnail?: TelegramPhotoSizeDto;
}

// ─── TelegramVoiceDto ───────────────────────────────────────────────────────

/**
 * Representa uma mensagem de voz recebida via Telegram.
 *
 * Contexto: módulo bot, DTOs de entrada do webhook.
 */
export class TelegramVoiceDto {
  @IsString()
  file_id: string;

  @IsString()
  file_unique_id: string;

  @IsInt()
  @Min(0)
  duration: number;

  @IsOptional()
  @IsString()
  mime_type?: string;

  @IsOptional()
  @IsInt()
  file_size?: number;
}

// ─── TelegramAudioDto ───────────────────────────────────────────────────────

/**
 * Representa um arquivo de áudio recebido via Telegram.
 *
 * Contexto: módulo bot, DTOs de entrada do webhook.
 */
export class TelegramAudioDto {
  @IsString()
  file_id: string;

  @IsString()
  file_unique_id: string;

  @IsInt()
  @Min(0)
  duration: number;

  @IsOptional()
  @IsString()
  performer?: string;

  @IsOptional()
  @IsString()
  title?: string;

  @IsOptional()
  @IsString()
  mime_type?: string;

  @IsOptional()
  @IsInt()
  file_size?: number;

  @IsOptional()
  @ValidateNested()
  @Type(() => TelegramPhotoSizeDto)
  thumbnail?: TelegramPhotoSizeDto;
}

// ─── TelegramDocumentDto ────────────────────────────────────────────────────

/**
 * Representa um documento ou arquivo genérico recebido via Telegram.
 *
 * Contexto: módulo bot, DTOs de entrada do webhook.
 */
export class TelegramDocumentDto {
  @IsString()
  file_id: string;

  @IsString()
  file_unique_id: string;

  @IsOptional()
  @IsString()
  file_name?: string;

  @IsOptional()
  @IsString()
  mime_type?: string;

  @IsOptional()
  @IsInt()
  file_size?: number;

  @IsOptional()
  @ValidateNested()
  @Type(() => TelegramPhotoSizeDto)
  thumbnail?: TelegramPhotoSizeDto;
}

// ─── TelegramStickerDto ─────────────────────────────────────────────────────

/**
 * Representa um sticker recebido via Telegram (estático, animado ou em vídeo).
 *
 * Contexto: módulo bot, DTOs de entrada do webhook.
 */
export class TelegramStickerDto {
  @IsString()
  file_id: string;

  @IsString()
  file_unique_id: string;

  @IsString()
  type: string;

  @IsInt()
  @Min(0)
  width: number;

  @IsInt()
  @Min(0)
  height: number;

  @IsBoolean()
  is_animated: boolean;

  @IsBoolean()
  is_video: boolean;

  @IsOptional()
  @IsString()
  emoji?: string;

  @IsOptional()
  @IsString()
  set_name?: string;

  @IsOptional()
  @IsInt()
  file_size?: number;

  @IsOptional()
  @ValidateNested()
  @Type(() => TelegramPhotoSizeDto)
  thumbnail?: TelegramPhotoSizeDto;
}

// ─── TelegramLocationDto ────────────────────────────────────────────────────

/**
 * Representa uma localização geográfica enviada via Telegram.
 *
 * Contexto: módulo bot, DTOs de entrada do webhook.
 */
export class TelegramLocationDto {
  @IsNumber()
  latitude: number;

  @IsNumber()
  longitude: number;
}

// ─── TelegramContactDto ─────────────────────────────────────────────────────

/**
 * Representa um contato de telefone compartilhado via Telegram.
 *
 * Contexto: módulo bot, DTOs de entrada do webhook.
 */
export class TelegramContactDto {
  @IsString()
  phone_number: string;

  @IsString()
  first_name: string;

  @IsOptional()
  @IsString()
  last_name?: string;

  @IsOptional()
  @IsInt()
  user_id?: number;
}

// ─── TelegramFileDto ────────────────────────────────────────────────────────

/**
 * Representa os metadados de um arquivo no Telegram (retorno de getFile).
 *
 * Contexto: módulo bot, DTOs de entrada do webhook.
 */
export class TelegramFileDto {
  @IsString()
  file_id: string;

  @IsString()
  file_unique_id: string;

  @IsOptional()
  @IsInt()
  file_size?: number;

  @IsOptional()
  @IsString()
  file_path?: string;
}

// ─── TelegramReactionTypeDto ────────────────────────────────────────────────

/**
 * Representa o tipo de uma reação de mensagem no Telegram (emoji padrão ou customizado).
 *
 * Contexto: módulo bot, DTOs de entrada do webhook.
 */
export class TelegramReactionTypeDto {
  @IsEnum(['emoji', 'custom_emoji'])
  type: 'emoji' | 'custom_emoji';

  @IsOptional()
  @IsString()
  emoji?: string;

  @IsOptional()
  @IsString()
  custom_emoji_id?: string;
}

// ─── TelegramMessageReactionDto ─────────────────────────────────────────────

/**
 * Representa o evento de reação a uma mensagem no Telegram.
 * Contém a lista de reações anteriores e novas do usuário.
 *
 * Contexto: módulo bot, DTOs de entrada do webhook.
 */
export class TelegramMessageReactionDto {
  @ValidateNested()
  @Type(() => TelegramChatDto)
  chat: TelegramChatDto;

  @IsInt()
  message_id: number;

  @IsOptional()
  @ValidateNested()
  @Type(() => TelegramUserDto)
  user?: TelegramUserDto;

  @IsInt()
  date: number;

  @IsArray()
  @ValidateNested({ each: true })
  @Type(() => TelegramReactionTypeDto)
  old_reaction: TelegramReactionTypeDto[];

  @IsArray()
  @ValidateNested({ each: true })
  @Type(() => TelegramReactionTypeDto)
  new_reaction: TelegramReactionTypeDto[];
}

// ─── TelegramMessageDto ─────────────────────────────────────────────────────

/**
 * Representa uma mensagem recebida ou enviada no Telegram.
 * Suporta texto, mídia, localização, contato e resposta encadeada.
 *
 * Contexto: módulo bot, DTOs de entrada do webhook.
 */
export class TelegramMessageDto {
  @IsInt()
  @Min(1)
  message_id: number;

  @IsOptional()
  @ValidateNested()
  @Type(() => TelegramUserDto)
  from?: TelegramUserDto;

  @ValidateNested()
  @Type(() => TelegramChatDto)
  chat: TelegramChatDto;

  @IsInt()
  @Min(0)
  date: number;

  @IsOptional()
  @IsString()
  @MaxLength(4096)
  text?: string;

  @IsOptional()
  @IsArray()
  @ValidateNested({ each: true })
  @Type(() => TelegramPhotoSizeDto)
  photo?: TelegramPhotoSizeDto[];

  @IsOptional()
  @ValidateNested()
  @Type(() => TelegramVideoDto)
  video?: TelegramVideoDto;

  @IsOptional()
  @ValidateNested()
  @Type(() => TelegramVoiceDto)
  voice?: TelegramVoiceDto;

  @IsOptional()
  @ValidateNested()
  @Type(() => TelegramAudioDto)
  audio?: TelegramAudioDto;

  @IsOptional()
  @ValidateNested()
  @Type(() => TelegramDocumentDto)
  document?: TelegramDocumentDto;

  @IsOptional()
  @ValidateNested()
  @Type(() => TelegramStickerDto)
  sticker?: TelegramStickerDto;

  @IsOptional()
  @ValidateNested()
  @Type(() => TelegramLocationDto)
  location?: TelegramLocationDto;

  @IsOptional()
  @ValidateNested()
  @Type(() => TelegramContactDto)
  contact?: TelegramContactDto;

  @IsOptional()
  @IsString()
  @MaxLength(1024)
  caption?: string;

  @IsOptional()
  @ValidateNested()
  @Type(() => TelegramMessageDto)
  reply_to_message?: TelegramMessageDto;

  @IsOptional()
  @IsInt()
  edit_date?: number;
}

// ─── TelegramWebhookInfoDto ─────────────────────────────────────────────────

/**
 * Informações do webhook registrado no Telegram (retorno de getWebhookInfo).
 *
 * Contexto: módulo bot, DTOs de entrada do webhook.
 */
export class TelegramWebhookInfoDto {
  @IsString()
  url: string;

  @IsBoolean()
  has_custom_certificate: boolean;

  @IsInt()
  pending_update_count: number;

  @IsOptional()
  @IsInt()
  last_error_date?: number;

  @IsOptional()
  @IsString()
  last_error_message?: string;
}

// ─── TelegramUpdateDto (root) ───────────────────────────────────────────────

/**
 * DTO raiz de uma atualização recebida do Telegram via webhook ou getUpdates.
 * Contém exatamente um dos campos opcionais preenchido por atualização.
 *
 * Contexto: módulo bot, DTOs de entrada do webhook.
 */
export class TelegramUpdateDto {
  @IsInt()
  @Min(0)
  update_id: number;

  @IsOptional()
  @ValidateNested()
  @Type(() => TelegramMessageDto)
  message?: TelegramMessageDto;

  @IsOptional()
  @ValidateNested()
  @Type(() => TelegramMessageDto)
  edited_message?: TelegramMessageDto;

  @IsOptional()
  @ValidateNested()
  @Type(() => TelegramMessageReactionDto)
  message_reaction?: TelegramMessageReactionDto;
}
