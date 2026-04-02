import { Injectable } from '@nestjs/common';
import { UazapiWebhookDto } from './uazapi.dto';
import { NormalizedUazapiEvent } from '../../models/uazapi.model';
import {
  JsonRecord,
  cloneRecord,
  getBoolean,
  getNumber,
  getRecord,
  getString,
  isRecord,
  readString,
} from '../../../../shared/utils/type-guards';

/**
 * Normalizador de payloads da Uazapi para o formato interno do gateway.
 */
@Injectable()
export class UazapiProvider {
  /**
   * Converte o payload recebido da Uazapi em evento normalizado.
   */
  normalize(token: string, payload: UazapiWebhookDto): NormalizedUazapiEvent {
    // Prefer the raw original message over the DTO message, because
    // ValidationPipe(whitelist:true) strips unknown fields from the DTO.
    const rawPayload = (payload as Record<string, unknown>).raw as
      | Record<string, unknown>
      | undefined;
    const rawMessage = rawPayload?.message;
    const typedMessage = (
      isRecord(rawMessage) ? rawMessage : payload.message
    ) as UazapiWebhookDto['message'];
    const messageRecord: JsonRecord = isRecord(typedMessage)
      ? (typedMessage as JsonRecord)
      : {};
    const content = getRecord(messageRecord, 'content') ?? {};

    const body =
      typedMessage?.body ??
      this.firstString([
        readString(messageRecord.text),
        readString(content.text),
        readString(content.caption),
        readString(content.URL),
        readString(content.degreesLatitude),
      ]) ??
      '';

    const mediaType =
      typedMessage?.type ??
      readString(messageRecord.mediaType) ??
      readString(messageRecord.messageType) ??
      readString(content.type) ??
      'text';

    const chatid =
      readString(messageRecord.chatid) ??
      readString(messageRecord.from) ??
      getString(messageRecord, 'sender');

    const direction = getBoolean(messageRecord, 'fromMe')
      ? 'outgoing'
      : 'incoming';
    const messageId = typedMessage?.id ?? readString(messageRecord.messageid);

    const timestamp =
      typedMessage?.timestamp ??
      getNumber(messageRecord, 'messageTimestamp') ??
      getString(messageRecord, 'messageTimestamp');

    const media = getRecord(messageRecord, 'content');

    const mediaUrl = this.firstString([
      readString(content.URL),
      readString(content.url),
      readString(content.mediaUrl),
      readString(content.media_url),
      readString(content.file),
    ]);

    const mimeType = this.firstString([
      readString(messageRecord.mimetype),
      readString(messageRecord.mimeType),
      readString(content.mimetype),
      readString(content.mimeType),
    ]);

    const fileName = this.firstString([
      readString(messageRecord.fileName),
      readString(messageRecord.filename),
      readString(content.fileName),
      readString(content.filename),
    ]);

    const senderPhoto = this.firstString([
      readString(payload.chat?.imagePreview as string | undefined),
      readString(payload.chat?.image as string | undefined),
    ]);

    const eventType =
      payload.EventType ??
      (payload as Record<string, unknown>).event_type ??
      (payload as Record<string, unknown>).event ??
      'messages';

    const presence = (payload as Record<string, unknown>).presence as
      | string
      | undefined;
    if (presence && ['composing', 'recording', 'paused'].includes(presence)) {
      return {
        provider: 'uazapi',
        event_type: 'presence',
        presence: presence as 'composing' | 'recording' | 'paused',
        is_typing: presence === 'composing' || presence === 'recording',
        number: (payload as Record<string, unknown>).number as
          | string
          | undefined,
        instance_webhook_token: token,
        owner: payload.owner,
        base_url: payload.BaseUrl,
        chat: payload.chat,
        raw: cloneRecord(payload),
      };
    }

    return {
      provider: 'uazapi',
      event_type: eventType,
      instance_webhook_token: token,
      owner: payload.owner,
      base_url: payload.BaseUrl,
      message: typedMessage
        ? {
            id: messageId,
            from:
              readString(messageRecord.sender) ??
              readString(messageRecord.from) ??
              chatid ??
              undefined,
            to: readString(messageRecord.to) ?? chatid ?? undefined,
            chatid,
            body,
            type: mediaType,
            timestamp,
            fromMe: getBoolean(messageRecord, 'fromMe') ?? false,
            media,
            mediaUrl,
            mimeType,
            fileName,
            senderPhoto,
          }
        : undefined,
      direction,
      chat: payload.chat,
      raw: cloneRecord(payload),
    };
  }

  /**
   * Retorna o primeiro valor nao vazio do array informado.
   *
   * @param values - Lista de candidatos a string
   * @returns Primeiro valor nao vazio ou undefined
   */
  private firstString(values: Array<string | undefined>): string | undefined {
    return values.find(
      (value) => typeof value === 'string' && value.length > 0,
    );
  }
}
