import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { AxiosError, AxiosInstance } from 'axios';
import { ZapiConfiguration } from '../../../../core/config/configuration';
import { AbstractHttpClient } from '../../../../shared/http/abstract-http-client';
import { parsePositiveTimeout } from '../../../../shared/utils/timeout.util';

import {
  ZapiSendMediaRequest,
  ZapiSendResponse,
  ZapiSendTextRequest,
  ZapiStatusResponse,
} from '../../models/zapi.model';

export type {
  ZapiSendMediaRequest,
  ZapiSendResponse,
  ZapiSendTextRequest,
  ZapiStatusResponse,
};
/**
 * Cliente HTTP para integracao com endpoints da Z-API.
 */
@Injectable()
export class ZapiClient extends AbstractHttpClient {
  protected readonly logger = new Logger(ZapiClient.name);
  private readonly axiosInstance: AxiosInstance;
  private readonly config: ZapiConfiguration;

  constructor(private readonly configService: ConfigService) {
    super();

    this.config = this.configService.get<ZapiConfiguration>('zapi') ?? {
      baseUrl:
        this.configService.get<string>('ZAPI_BASE_URL') ??
        'https://api.z-api.io',
      clientToken: '',
    };

    const baseUrl =
      this.config.baseUrl ??
      this.configService.get<string>('ZAPI_BASE_URL') ??
      'https://api.z-api.io';
    const timeout = parsePositiveTimeout(
      this.config.timeout ??
        this.configService.get<string | number>('ZAPI_HTTP_TIMEOUT'),
      30000,
    );

    this.axiosInstance = this.createAxiosInstance({
      baseURL: baseUrl,
      timeout,
      headers: {
        'Content-Type': 'application/json',
        'Client-Token': this.config.clientToken,
      },
    });
  }

  /**
   * Envia mensagem de texto pela Z-API.
   */
  async sendText(
    instanceId: string,
    instanceToken: string,
    request: ZapiSendTextRequest,
  ): Promise<ZapiSendResponse> {
    try {
      const response = await this.axiosInstance.post<ZapiSendResponse>(
        `/instances/${instanceId}/token/${instanceToken}/send-text`,
        request,
      );
      return response.data;
    } catch (error) {
      this.handleError('sendText', error);
      throw error;
    }
  }

  /**
   * Envia imagem pela Z-API.
   */
  async sendImage(
    instanceId: string,
    instanceToken: string,
    request: ZapiSendMediaRequest,
  ): Promise<ZapiSendResponse> {
    try {
      const response = await this.axiosInstance.post<ZapiSendResponse>(
        `/instances/${instanceId}/token/${instanceToken}/send-image`,
        {
          phone: request.phone,
          image: request.image,
          caption: request.caption,
        },
      );
      return response.data;
    } catch (error) {
      this.handleError('sendImage', error);
      throw error;
    }
  }

  /**
   * Envia documento pela Z-API.
   */
  async sendDocument(
    instanceId: string,
    instanceToken: string,
    request: ZapiSendMediaRequest,
  ): Promise<ZapiSendResponse> {
    try {
      const response = await this.axiosInstance.post<ZapiSendResponse>(
        `/instances/${instanceId}/token/${instanceToken}/send-document`,
        {
          phone: request.phone,
          document: request.document,
          fileName: request.fileName,
        },
      );
      return response.data;
    } catch (error) {
      this.handleError('sendDocument', error);
      throw error;
    }
  }

  /**
   * Envia audio pela Z-API.
   */
  async sendAudio(
    instanceId: string,
    instanceToken: string,
    request: ZapiSendMediaRequest,
  ): Promise<ZapiSendResponse> {
    try {
      const response = await this.axiosInstance.post<ZapiSendResponse>(
        `/instances/${instanceId}/token/${instanceToken}/send-audio`,
        {
          phone: request.phone,
          audio: request.audio,
        },
      );
      return response.data;
    } catch (error) {
      this.handleError('sendAudio', error);
      throw error;
    }
  }

  /**
   * Envia video pela Z-API.
   */
  async sendVideo(
    instanceId: string,
    instanceToken: string,
    request: ZapiSendMediaRequest,
  ): Promise<ZapiSendResponse> {
    try {
      const response = await this.axiosInstance.post<ZapiSendResponse>(
        `/instances/${instanceId}/token/${instanceToken}/send-video`,
        {
          phone: request.phone,
          video: request.video,
          caption: request.caption,
        },
      );
      return response.data;
    } catch (error) {
      this.handleError('sendVideo', error);
      throw error;
    }
  }

  /**
   * Consulta o status de conexao da instancia na Z-API.
   */
  async getStatus(
    instanceId: string,
    instanceToken: string,
  ): Promise<ZapiStatusResponse> {
    try {
      const response = await this.axiosInstance.get<ZapiStatusResponse>(
        `/instances/${instanceId}/token/${instanceToken}/status`,
      );
      return response.data;
    } catch (error) {
      this.handleError('getStatus', error);
      throw error;
    }
  }

  /**
   * Recupera o QR code de pareamento da instancia na Z-API.
   */
  async getQrCode(
    instanceId: string,
    instanceToken: string,
  ): Promise<string | null> {
    try {
      const response = await this.axiosInstance.get<{ value?: string }>(
        `/instances/${instanceId}/token/${instanceToken}/qr-code/image`,
      );
      return response.data.value ?? null;
    } catch (error) {
      const message = error instanceof Error ? error.message : 'unknown error';
      this.logger.error('[Z-API] getQrCode failed, returning null fallback', {
        message,
      });
      return null;
    }
  }

  /**
   * Desconecta uma instancia ativa na Z-API.
   */
  async disconnect(instanceId: string, instanceToken: string): Promise<void> {
    try {
      await this.axiosInstance.delete(
        `/instances/${instanceId}/token/${instanceToken}/disconnect`,
      );
    } catch (error) {
      this.handleError('disconnect', error);
      throw error;
    }
  }

  /**
   * Registra o erro da chamada HTTP e relanca para o chamador.
   *
   * @param method - Nome do metodo onde o erro ocorreu
   * @param error - Objeto de erro capturado
   */
  private handleError(method: string, error: unknown): void {
    if (error instanceof AxiosError) {
      this.logger.error(
        `[Z-API] ${method} failed: ${error.message}`,
        error.response?.data,
      );
      return;
    }

    this.logger.error(`[Z-API] ${method} failed with unknown error`, error);
  }
}
