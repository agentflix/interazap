import { Injectable, Logger } from '@nestjs/common';
import { ZapiClient, ZapiSendResponse } from './zapi.client';
import { ZapiNormalizer } from './zapi.normalizer';
import {
  WhatsAppProvider,
  SendTextRequest,
  SendMediaRequest,
  SendMessageResult,
  InstanceStatus,
  NormalizedWebhookEvent,
} from '../../contracts/provider.interface';
import { ZapiWebhookPayload } from '../../models/zapi.model';

/**
 * Adaptador do provedor Z-API para envio e normalizacao de webhooks.
 */
@Injectable()
export class ZapiAdapter implements WhatsAppProvider {
  private readonly logger = new Logger(ZapiAdapter.name);
  readonly name = 'zapi' as const;

  constructor(
    private readonly client: ZapiClient,
    private readonly normalizer: ZapiNormalizer,
  ) {}

  /**
   * Envia mensagem de texto usando o client da Z-API.
   */
  async sendText(
    instanceToken: string,
    request: SendTextRequest,
  ): Promise<SendMessageResult> {
    try {
      const { instanceId, token } = this.parseInstanceToken(instanceToken);
      const response = await this.client.sendText(instanceId, token, {
        phone: request.to,
        message: request.text,
      });

      return {
        success: true,
        messageId: response.zapiMessageId ?? response.messageId ?? response.id,
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
   * Envia mensagem de midia usando o endpoint adequado da Z-API.
   */
  async sendMedia(
    instanceToken: string,
    request: SendMediaRequest,
  ): Promise<SendMessageResult> {
    try {
      const { instanceId, token } = this.parseInstanceToken(instanceToken);
      let response: ZapiSendResponse;

      switch (request.type) {
        case 'image':
          response = await this.client.sendImage(instanceId, token, {
            phone: request.to,
            image: request.mediaUrl,
            caption: request.caption,
          });
          break;
        case 'video':
          response = await this.client.sendVideo(instanceId, token, {
            phone: request.to,
            video: request.mediaUrl,
            caption: request.caption,
          });
          break;
        case 'audio':
          response = await this.client.sendAudio(instanceId, token, {
            phone: request.to,
            audio: request.mediaUrl,
          });
          break;
        case 'document':
          response = await this.client.sendDocument(instanceId, token, {
            phone: request.to,
            document: request.mediaUrl,
            fileName: request.fileName,
          });
          break;
        default: {
          const unsupportedType: string = request.type as string;
          return {
            success: false,
            error: `Unsupported media type: ${unsupportedType}`,
          };
        }
      }

      return {
        success: true,
        messageId: response.zapiMessageId ?? response.messageId ?? response.id,
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
   * Consulta status de conexao da instancia no provedor Z-API.
   */
  async getStatus(instanceToken: string): Promise<InstanceStatus> {
    try {
      const { instanceId, token } = this.parseInstanceToken(instanceToken);
      const status = await this.client.getStatus(instanceId, token);

      return {
        connected: status.connected,
        loggedIn: status.connected,
        phone: status.session,
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
   * Desconecta a instancia no provedor Z-API.
   */
  async disconnect(instanceToken: string): Promise<void> {
    const { instanceId, token } = this.parseInstanceToken(instanceToken);
    await this.client.disconnect(instanceId, token);
  }

  /**
   * Recupera o QR code da instancia no provedor Z-API.
   */
  async getQrCode(instanceToken: string): Promise<string | null> {
    const { instanceId, token } = this.parseInstanceToken(instanceToken);
    return this.client.getQrCode(instanceId, token);
  }

  /**
   * Normaliza payload bruto recebido da Z-API para o contrato interno.
   */
  normalizeWebhook(
    webhookToken: string,
    rawPayload: unknown,
    tenantId = '',
    instanceId = '',
  ): NormalizedWebhookEvent {
    return this.normalizer.normalize(
      webhookToken,
      rawPayload as ZapiWebhookPayload,
      tenantId,
      instanceId,
    );
  }

  /**
   * Decompoe o token composto de instancia no formato 'instanceId:token'.
   *
   * @param instanceToken - Token no formato 'instanceId:token'
   * @returns Objeto com instanceId e token separados
   * @throws Error quando o formato nao e valido
   */
  private parseInstanceToken(instanceToken: string): {
    instanceId: string;
    token: string;
  } {
    const parts = instanceToken.split(':');
    if (parts.length !== 2) {
      throw new Error(
        `Invalid instance token format. Expected "instanceId:token", got "${instanceToken}"`,
      );
    }
    return { instanceId: parts[0], token: parts[1] };
  }
}
