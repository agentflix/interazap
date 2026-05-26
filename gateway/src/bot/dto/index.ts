/**
 * Re-exporta todos os DTOs e interfaces do módulo bot relacionados à Telegram Bot API.
 *
 * Contexto: módulo bot.
 */
export {
  TelegramUpdateDto,
  TelegramMessageDto,
  TelegramUserDto,
  TelegramChatDto,
  TelegramPhotoSizeDto,
  TelegramVideoDto,
  TelegramVoiceDto,
  TelegramAudioDto,
  TelegramDocumentDto,
  TelegramStickerDto,
  TelegramLocationDto,
  TelegramContactDto,
  TelegramFileDto,
  TelegramReactionTypeDto,
  TelegramMessageReactionDto,
  TelegramWebhookInfoDto,
} from './telegram-update.dto';

export type { TgResult } from './telegram-result.interface';
