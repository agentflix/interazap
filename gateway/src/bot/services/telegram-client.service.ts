import { Injectable, Logger } from '@nestjs/common';
import { HttpService } from '@nestjs/axios';
import { firstValueFrom } from 'rxjs';
import { AxiosError } from 'axios';
import { TgResult } from '../dto/telegram-result.interface';

/**
 * Cliente HTTP para a Telegram Bot API.
 *
 * Contexto: módulo bot. Encapsula todas as chamadas à API do Telegram,
 * gerenciando erros, logs e mascaramento do token nos registros.
 */
@Injectable()
export class TelegramClientService {
  private readonly logger = new Logger(TelegramClientService.name);
  private readonly BASE_URL = 'https://api.telegram.org';

  constructor(private readonly httpService: HttpService) {}

  // ─── Métodos Públicos da API ───────────────────────────────

  /**
   * Envia uma mensagem de texto para um chat.
   * @param botToken Token do bot
   * @param chatId Identificador do chat de destino
   * @param text Texto da mensagem
   * @param replyToMessageId ID da mensagem a ser respondida (opcional)
   * @returns Resposta da API do Telegram
   */
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

  /**
   * Envia uma foto para um chat.
   * @param botToken Token do bot
   * @param chatId Identificador do chat de destino
   * @param photo file_id, URL ou upload da foto
   * @param caption Legenda opcional
   */
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

  /**
   * Envia um vídeo para um chat.
   * @param botToken Token do bot
   * @param chatId Identificador do chat de destino
   * @param video file_id, URL ou upload do vídeo
   * @param caption Legenda opcional
   */
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

  /**
   * Envia um áudio de voz para um chat.
   * @param botToken Token do bot
   * @param chatId Identificador do chat de destino
   * @param voice file_id, URL ou upload do áudio
   * @param caption Legenda opcional
   */
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

  /**
   * Envia um arquivo de áudio para um chat.
   * @param botToken Token do bot
   * @param chatId Identificador do chat de destino
   * @param audio file_id, URL ou upload do áudio
   * @param caption Legenda opcional
   */
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

  /**
   * Envia um documento para um chat.
   * @param botToken Token do bot
   * @param chatId Identificador do chat de destino
   * @param document file_id, URL ou upload do documento
   * @param caption Legenda opcional
   */
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

  /**
   * Envia uma localização geográfica para um chat.
   * @param botToken Token do bot
   * @param chatId Identificador do chat de destino
   * @param latitude Latitude da localização
   * @param longitude Longitude da localização
   */
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

  /**
   * Envia um sticker para um chat.
   * @param botToken Token do bot
   * @param chatId Identificador do chat de destino
   * @param sticker file_id ou URL do sticker
   */
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

  /**
   * Envia uma ação de chat (ex: "digitando...") para indicar atividade ao usuário.
   * @param botToken Token do bot
   * @param chatId Identificador do chat de destino
   * @param action Tipo de ação (atualmente apenas 'typing')
   */
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

  /**
   * Registra um webhook na Telegram Bot API.
   * @param botToken Token do bot
   * @param url URL HTTPS do webhook
   * @param secretToken Token secreto enviado pelo Telegram em cada requisição
   */
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

  /**
   * Remove o webhook registrado na Telegram Bot API.
   * @param botToken Token do bot
   * @param dropPendingUpdates Se true, descarta atualizações pendentes na fila
   */
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

  /**
   * Obtém informações sobre o webhook registrado (URL, erros, atualizações pendentes).
   * @param botToken Token do bot
   */
  async getWebhookInfo(botToken: string): Promise<TgResult<any>> {
    return this.callApi(botToken, 'getWebhookInfo', {});
  }

  /**
   * Obtém as informações básicas do bot (id, nome, username).
   * @param botToken Token do bot
   */
  async getMe(botToken: string): Promise<TgResult<any>> {
    return this.callApi(botToken, 'getMe', {});
  }

  /**
   * Obtém os metadados de um arquivo armazenado no Telegram.
   * @param botToken Token do bot
   * @param fileId Identificador único do arquivo
   */
  async getFile(botToken: string, fileId: string): Promise<TgResult<any>> {
    return this.callApi(botToken, 'getFile', { file_id: fileId });
  }

  /**
   * Busca atualizações pendentes via long-polling.
   * @param botToken Token do bot
   * @param offset Identificador mínimo do update a retornar (para avançar o cursor)
   * @param timeout Tempo máximo de espera em segundos para long-polling
   * @param allowedUpdates Tipos de atualização a receber
   * @returns Lista de updates recebidos
   */
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

  /**
   * Constrói a URL para download direto de um arquivo do Telegram.
   * @param botToken Token do bot
   * @param filePath Caminho do arquivo retornado por getFile
   * @returns URL completa para download
   */
  getFileUrl(botToken: string, filePath: string): string {
    return `${this.BASE_URL}/file/bot${botToken}/${filePath}`;
  }

  // ─── Private ───────────────────────────────────────────────

  /**
   * Executa uma chamada POST à Telegram Bot API com tratamento de erros centralizado.
   * @param botToken Token do bot (mascarado nos logs)
   * @param method Nome do método da API (ex: 'sendMessage')
   * @param body Corpo da requisição
   * @returns Resposta tipada da API
   * @throws AxiosError ou Error em caso de falha
   */
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

  /**
   * Mascara o token do bot para exibição segura em logs.
   * @param token Token completo do bot
   * @returns Versão mascarada do token (ex: "123456:***")
   */
  private maskToken(token: string): string {
    const colonIndex = token.indexOf(':');
    if (colonIndex === -1) {
      return token.length > 5 ? `${token.substring(0, 5)}***` : '***';
    }
    return `${token.substring(0, colonIndex + 1)}***`;
  }
}
