import { Injectable, Logger } from '@nestjs/common';
import { HttpService } from '@nestjs/axios';
import { firstValueFrom } from 'rxjs';
import { AxiosError } from 'axios';
import { TgResult } from '../dto/telegram-result.interface';

@Injectable()
export class TelegramClientService {
  private readonly logger = new Logger(TelegramClientService.name);
  private readonly BASE_URL = 'https://api.telegram.org';

  constructor(private readonly httpService: HttpService) {}

  // ─── Public API Methods ────────────────────────────────────

  async sendMessage(
    botToken: string,
    chatId: string,
    text: string,
    replyToMessageId?: number,
  ): Promise<TgResult<any>> {
    return this.callApi(botToken, 'sendMessage', {
      chat_id: chatId,
      text,
      ...(replyToMessageId !== undefined && {
        reply_parameters: { message_id: replyToMessageId },
      }),
    });
  }

  async sendPhoto(
    botToken: string,
    chatId: string,
    photo: string,
    caption?: string,
  ): Promise<TgResult<any>> {
    return this.callApi(botToken, 'sendPhoto', {
      chat_id: chatId,
      photo,
      ...(caption !== undefined && { caption }),
    });
  }

  async sendVideo(
    botToken: string,
    chatId: string,
    video: string,
    caption?: string,
  ): Promise<TgResult<any>> {
    return this.callApi(botToken, 'sendVideo', {
      chat_id: chatId,
      video,
      ...(caption !== undefined && { caption }),
    });
  }

  async sendVoice(
    botToken: string,
    chatId: string,
    voice: string,
    caption?: string,
  ): Promise<TgResult<any>> {
    return this.callApi(botToken, 'sendVoice', {
      chat_id: chatId,
      voice,
      ...(caption !== undefined && { caption }),
    });
  }

  async sendAudio(
    botToken: string,
    chatId: string,
    audio: string,
    caption?: string,
  ): Promise<TgResult<any>> {
    return this.callApi(botToken, 'sendAudio', {
      chat_id: chatId,
      audio,
      ...(caption !== undefined && { caption }),
    });
  }

  async sendDocument(
    botToken: string,
    chatId: string,
    document: string,
    caption?: string,
  ): Promise<TgResult<any>> {
    return this.callApi(botToken, 'sendDocument', {
      chat_id: chatId,
      document,
      ...(caption !== undefined && { caption }),
    });
  }

  async sendLocation(
    botToken: string,
    chatId: string,
    latitude: number,
    longitude: number,
  ): Promise<TgResult<any>> {
    return this.callApi(botToken, 'sendLocation', {
      chat_id: chatId,
      latitude,
      longitude,
    });
  }

  async sendSticker(
    botToken: string,
    chatId: string,
    sticker: string,
  ): Promise<TgResult<any>> {
    return this.callApi(botToken, 'sendSticker', {
      chat_id: chatId,
      sticker,
    });
  }

  async sendChatAction(
    botToken: string,
    chatId: string,
    action: 'typing',
  ): Promise<TgResult<boolean>> {
    return this.callApi<boolean>(botToken, 'sendChatAction', {
      chat_id: chatId,
      action,
    });
  }

  async setWebhook(
    botToken: string,
    url: string,
    secretToken: string,
  ): Promise<TgResult<boolean>> {
    return this.callApi<boolean>(botToken, 'setWebhook', {
      url,
      secret_token: secretToken,
    });
  }

  async deleteWebhook(
    botToken: string,
    dropPendingUpdates?: boolean,
  ): Promise<TgResult<boolean>> {
    return this.callApi<boolean>(botToken, 'deleteWebhook', {
      ...(dropPendingUpdates !== undefined && {
        drop_pending_updates: dropPendingUpdates,
      }),
    });
  }

  async getWebhookInfo(botToken: string): Promise<TgResult<any>> {
    return this.callApi(botToken, 'getWebhookInfo', {});
  }

  async getMe(botToken: string): Promise<TgResult<any>> {
    return this.callApi(botToken, 'getMe', {});
  }

  async getFile(botToken: string, fileId: string): Promise<TgResult<any>> {
    return this.callApi(botToken, 'getFile', { file_id: fileId });
  }

  async getUpdates(
    botToken: string,
    offset?: number,
    timeout?: number,
    allowedUpdates?: string[],
  ): Promise<TgResult<any[]>> {
    return this.callApi<any[]>(botToken, 'getUpdates', {
      ...(offset !== undefined && { offset }),
      ...(timeout !== undefined && { timeout }),
      ...(allowedUpdates !== undefined && {
        allowed_updates: allowedUpdates,
      }),
    });
  }

  getFileUrl(botToken: string, filePath: string): string {
    return `${this.BASE_URL}/file/bot${botToken}/${filePath}`;
  }

  // ─── Private ───────────────────────────────────────────────

  private async callApi<T>(
    botToken: string,
    method: string,
    body: Record<string, unknown>,
  ): Promise<TgResult<T>> {
    const url = `${this.BASE_URL}/bot${botToken}/${method}`;
    const masked = this.maskToken(botToken);

    this.logger.debug(`Telegram API → ${method} [token=${masked}]`);

    try {
      const response = await firstValueFrom(
        this.httpService.post<TgResult<T>>(url, body, {
          headers: { 'Content-Type': 'application/json' },
        }),
      );

      return response.data;
    } catch (error: unknown) {
      if (error instanceof AxiosError) {
        const status = error.response?.status ?? 'N/A';
        const desc =
          (error.response?.data as TgResult<unknown>)?.description ??
          error.message;

        this.logger.error(
          `Telegram API error on ${method} [token=${masked}]: HTTP ${status} — ${desc}`,
        );

        throw error;
      }

      if (
        error instanceof Error &&
        error.message?.toLowerCase().includes('timeout')
      ) {
        this.logger.error(
          `Telegram API timeout on ${method} [token=${masked}]`,
        );
        throw error;
      }

      this.logger.error(
        `Telegram API unknown error on ${method} [token=${masked}]: ${String(error)}`,
      );
      throw new Error(`Telegram API call failed: ${method} [token=${masked}]`);
    }
  }

  private maskToken(token: string): string {
    const colonIndex = token.indexOf(':');
    if (colonIndex === -1) {
      return token.length > 5 ? `${token.substring(0, 5)}***` : '***';
    }
    return `${token.substring(0, colonIndex + 1)}***`;
  }
}
