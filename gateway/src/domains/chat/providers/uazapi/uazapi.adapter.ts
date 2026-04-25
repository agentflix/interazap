import { Injectable, Logger } from '@nestjs/common';
import { UazapiClient } from './uazapi.client';
import { UazapiProvider } from './uazapi.provider';
import {
  InstanceStatus,
  NormalizedWebhookEvent,
  SendMediaRequest,
  SendMessageResult,
  SendTextRequest,
  WhatsAppProvider,
} from '../../contracts/provider.interface';
import { UazapiWebhookDto } from './uazapi.dto';
import {
  getBoolean,
  getRecord,
  getString,
} from '../../../../shared/utils/type-guards';

/**
 * Adaptador de provedor Uazapi para envio e normalizacao de webhooks.
 */
@Injectable()
export class UazapiAdapter implements WhatsAppProvider {
  private readonly logger = new Logger(UazapiAdapter.name);
  readonly name = 'uazapi' as const;

  constructor(
    private readonly client: UazapiClient,
    private readonly provider: UazapiProvider,
  ) {}

  /**
   * Envia mensagem de texto usando a integracao Uazapi.
   */
  async sendText(
    instanceToken: string,
    request: SendTextRequest,
  ): Promise<SendMessageResult> {
    try {
      const result = await this.client.sendText(instanceToken, {
        number: request.to,
        text: request.text,
        quoted: request.quotedMessageId,
      });

      const normalized = this.toRecord(result);
      return {
        success: true,
        messageId:
          getString(normalized, 'messageId') ??
          getString(normalized, 'id') ??
          getString(normalized, 'message_id') ??
          undefined,
      };
    } catch (error) {
      this.logger.error(`Failed to send text: ${(error as Error).message}`);
      return {
        success: false,
        error: (error as Error).message,
      };
    }
  }

  /**
   * Envia mensagem de midia usando a integracao Uazapi.
   */
  async sendMedia(
    instanceToken: string,
    request: SendMediaRequest,
  ): Promise<SendMessageResult> {
    try {
      const result = await this.client.sendFile(instanceToken, {
        number: request.to,
        media: request.mediaUrl,
        caption: request.caption,
        fileName: request.fileName,
        mimeType: request.mimeType,
      });

      const normalized = this.toRecord(result);
      return {
        success: true,
        messageId:
          getString(normalized, 'messageId') ??
          getString(normalized, 'id') ??
          getString(normalized, 'message_id') ??
          undefined,
      };
    } catch (error) {
      this.logger.error(`Failed to send media: ${(error as Error).message}`);
      return {
        success: false,
        error: (error as Error).message,
      };
    }
  }

  /**
   * Consulta status da instancia no provedor Uazapi.
   */
  async getStatus(instanceToken: string): Promise<InstanceStatus> {
    try {
      const result = await this.client.instanceStatus(instanceToken);
      const normalized = this.toRecord(result);
      const connection = getRecord(normalized, 'instance') ?? normalized;
      const status =
        getString(connection, 'status') ?? getString(normalized, 'status');
      const qrCode =
        getString(connection, 'qrcode') ??
        getString(connection, 'qr_code') ??
        getString(normalized, 'qrcode') ??
        null;

      const connected =
        getBoolean(connection, 'connected') ??
        (status ? status.toLowerCase() === 'connected' : false);

      return {
        connected,
        loggedIn: connected,
        qrCode: qrCode ?? undefined,
        phone:
          getString(connection, 'owner') ??
          getString(normalized, 'owner') ??
          undefined,
      };
    } catch (error) {
      this.logger.error(`Failed to get status: ${(error as Error).message}`);
      return {
        connected: false,
        loggedIn: false,
      };
    }
  }

  /**
   * Desconecta a instancia no provedor Uazapi.
   */
  async disconnect(instanceToken: string): Promise<void> {
    await this.client.disconnectInstance(instanceToken);
  }

  /**
   * Recupera QR code da instancia no provedor Uazapi.
   */
  async getQrCode(instanceToken: string): Promise<string | null> {
    const status = await this.getStatus(instanceToken);
    return status.qrCode ?? null;
  }

  /**
   * Normaliza payload bruto da Uazapi para o contrato interno.
   */
  normalizeWebhook(
    token: string,
    rawPayload: unknown,
  ): Promise<NormalizedWebhookEvent> {
    const normalized = this.provider.normalize(
      token,
      rawPayload as UazapiWebhookDto,
    );

    const direction =
      normalized.direction === 'outgoing' ? 'outbound' : 'inbound';

    return Promise.resolve({
      tenantId: '',
      instanceId: '',
      instanceWebhookToken: token,
      provider: 'uazapi',
      eventType: normalized.event_type,
      direction,
      message: normalized.message
        ? {
            id: normalized.message.id ?? '',
            from: normalized.message.from ?? '',
            to: normalized.message.to ?? '',
            type: this.mapMessageType(normalized.message.type),
            text: normalized.message.body,
            caption: normalized.message.body,
            mediaUrl: normalized.message.mediaUrl,
            mimeType: normalized.message.mimeType,
            fileName: normalized.message.fileName,
            timestamp: new Date(),
            isFromMe: normalized.message.fromMe ?? false,
            isGroup: false,
            senderPhoto: normalized.message.senderPhoto,
          }
        : undefined,
      connection:
        normalized.event_type === 'connection'
          ? {
              status:
                getString(this.toRecord(normalized.raw.instance), 'status') ??
                'unknown',
              connected:
                getBoolean(this.toRecord(normalized.raw.status), 'connected') ??
                false,
            }
          : undefined,
      rawPayload: this.toRecord(normalized.raw),
      idempotencyKey: `${token}:${normalized.event_type}:${normalized.message?.id ?? 'generic'}`,
      receivedAt: new Date(),
    });
  }

  /**
   * Coverte um valor desconhecido para Record<string, unknown> de forma segura.
   *
   * @param value - Valor a ser convertido
   * @returns Record vazio quando o valor nao e um objeto simples
   */
  private toRecord(value: unknown): Record<string, unknown> {
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
      return {};
    }

    return value as Record<string, unknown>;
  }

  /**
   * Mapeia tipo de mensagem textual para o tipo normalizado interno.
   *
   * @param type - Tipo de mensagem como string do provedor
   * @returns Tipo normalizado do contrato interno
   */
  private mapMessageType(
    type: string | undefined,
  ):
    | 'text'
    | 'image'
    | 'video'
    | 'audio'
    | 'document'
    | 'sticker'
    | 'location'
    | 'contact' {
    const normalized = (type ?? 'text').toLowerCase();

    if (normalized.includes('image')) return 'image';
    if (normalized.includes('video')) return 'video';
    if (normalized.includes('audio') || normalized.includes('voice'))
      return 'audio';
    if (normalized.includes('document') || normalized.includes('file'))
      return 'document';
    if (normalized.includes('sticker')) return 'sticker';
    if (normalized.includes('location')) return 'location';
    if (normalized.includes('contact')) return 'contact';
    return 'text';
  }
}
