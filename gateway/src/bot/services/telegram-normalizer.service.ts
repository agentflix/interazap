import { Injectable, Logger } from '@nestjs/common';
import {
  TelegramUpdateDto,
  TelegramMessageDto,
  TelegramUserDto,
} from '../dto/telegram-update.dto';

// ─── Interfaces ─────────────────────────────────────────────────────────────

export interface NormalizedTelegramEvent {
  provider: 'telegram';
  webhookToken: string;
  idempotencyKey: string;
  direction: 'inbound' | 'outbound';
  eventType: 'message' | 'message_status' | 'message_edit';

  // Contact/chat info
  chatId: string;
  remoteJid: string;
  senderName: string;
  senderUsername?: string;
  senderPhone?: string;
  isFromMe: boolean;

  // Message content
  messageId: string;
  type:
    | 'text'
    | 'image'
    | 'video'
    | 'audio'
    | 'document'
    | 'sticker'
    | 'location'
    | 'contact';
  text?: string;
  caption?: string;

  // Media
  fileId?: string;
  fileName?: string;
  mimeType?: string;
  fileSize?: number;

  // Location
  latitude?: number;
  longitude?: number;

  // Status
  edited?: boolean;
  editDate?: number;

  // Reaction
  reactions?: { type: string; emoji?: string }[];

  // Metadata
  timestamp: Date;
  rawUpdateId: number;
  replyToMessageId?: string;
}

interface MediaInfo {
  type: 'image' | 'video' | 'audio' | 'document' | 'sticker';
  fileId: string;
  fileName?: string;
  mimeType?: string;
  fileSize?: number;
}

// ─── Service ────────────────────────────────────────────────────────────────

@Injectable()
export class TelegramNormalizerService {
  private readonly logger = new Logger(TelegramNormalizerService.name);

  /**
   * Normalize a Telegram update into the platform's webhook event format.
   *
   * Mapping rules:
   * - update.message (from.is_bot=false) → direction: 'inbound', eventType: 'message'
   * - update.message (from.is_bot=true)  → direction: 'outbound', eventType: 'message'
   * - update.edited_message              → eventType: 'message_edit', edited: true
   * - update.message_reaction            → eventType: 'message_status'
   */
  normalize(
    webhookToken: string,
    update: TelegramUpdateDto,
  ): NormalizedTelegramEvent | null {
    if (update.message_reaction) {
      return this.normalizeReaction(webhookToken, update);
    }

    const message = update.edited_message ?? update.message;
    if (!message) {
      this.logger.warn(
        `Unsupported update type for update_id=${update.update_id}`,
      );
      return null;
    }

    const isEdited = !!update.edited_message;
    const isBot = message.from?.is_bot ?? false;
    const media = this.extractMediaInfo(message);
    const chatId = message.chat.id.toString();

    return {
      provider: 'telegram',
      webhookToken,
      idempotencyKey: `tg-${update.update_id}`,
      direction: isBot ? 'outbound' : 'inbound',
      eventType: isEdited ? 'message_edit' : 'message',

      // Contact/chat info
      chatId,
      remoteJid: chatId,
      senderName: message.from
        ? this.extractSenderName(message.from)
        : 'Unknown',
      senderUsername: message.from?.username,
      senderPhone: message.contact?.phone_number,
      isFromMe: isBot,

      // Message content
      messageId: message.message_id.toString(),
      type: this.resolveMessageType(message, media),
      text: message.text,
      caption: message.caption,

      // Media
      fileId: media?.fileId,
      fileName: media?.fileName,
      mimeType: media?.mimeType,
      fileSize: media?.fileSize,

      // Location
      latitude: message.location?.latitude,
      longitude: message.location?.longitude,

      // Status
      edited: isEdited || undefined,
      editDate: message.edit_date,

      // Metadata
      timestamp: new Date(message.date * 1000),
      rawUpdateId: update.update_id,
      replyToMessageId: message.reply_to_message?.message_id?.toString(),
    };
  }

  // ─── Private Helpers ────────────────────────────────────────

  private normalizeReaction(
    webhookToken: string,
    update: TelegramUpdateDto,
  ): NormalizedTelegramEvent {
    const reaction = update.message_reaction!;
    const chatId = reaction.chat.id.toString();

    return {
      provider: 'telegram',
      webhookToken,
      idempotencyKey: `tg-${update.update_id}`,
      direction: 'inbound',
      eventType: 'message_status',

      chatId,
      remoteJid: chatId,
      senderName: reaction.user
        ? this.extractSenderName(reaction.user)
        : 'Unknown',
      senderUsername: reaction.user?.username,
      isFromMe: reaction.user?.is_bot ?? false,

      messageId: reaction.message_id.toString(),
      type: 'text',

      reactions: reaction.new_reaction.map((r) => ({
        type: r.type,
        emoji: r.emoji,
      })),

      timestamp: new Date(reaction.date * 1000),
      rawUpdateId: update.update_id,
    };
  }

  private extractSenderName(from: TelegramUserDto): string {
    const parts = [from.first_name, from.last_name].filter(Boolean);
    return parts.join(' ') || 'Unknown';
  }

  private extractMediaInfo(message: TelegramMessageDto): MediaInfo | null {
    // Photo: use last item (highest resolution)
    if (message.photo?.length) {
      const photo = message.photo[message.photo.length - 1];
      return {
        type: 'image',
        fileId: photo.file_id,
        mimeType: 'image/jpeg',
        fileSize: photo.file_size,
      };
    }

    if (message.video) {
      return {
        type: 'video',
        fileId: message.video.file_id,
        mimeType: message.video.mime_type,
        fileSize: message.video.file_size,
      };
    }

    if (message.voice) {
      return {
        type: 'audio',
        fileId: message.voice.file_id,
        mimeType: message.voice.mime_type ?? 'audio/ogg',
        fileSize: message.voice.file_size,
      };
    }

    if (message.audio) {
      return {
        type: 'audio',
        fileId: message.audio.file_id,
        fileName: message.audio.title
          ? `${message.audio.performer ?? 'Unknown'} - ${message.audio.title}`
          : undefined,
        mimeType: message.audio.mime_type,
        fileSize: message.audio.file_size,
      };
    }

    if (message.document) {
      return {
        type: 'document',
        fileId: message.document.file_id,
        fileName: message.document.file_name,
        mimeType: message.document.mime_type,
        fileSize: message.document.file_size,
      };
    }

    if (message.sticker) {
      return {
        type: 'sticker',
        fileId: message.sticker.file_id,
        mimeType: message.sticker.is_animated
          ? 'application/x-tgsticker'
          : message.sticker.is_video
            ? 'video/webm'
            : 'image/webp',
        fileSize: message.sticker.file_size,
      };
    }

    return null;
  }

  private resolveMessageType(
    message: TelegramMessageDto,
    media: MediaInfo | null,
  ): NormalizedTelegramEvent['type'] {
    if (media) {
      return media.type;
    }
    if (message.location) {
      return 'location';
    }
    if (message.contact) {
      return 'contact';
    }
    return 'text';
  }
}
