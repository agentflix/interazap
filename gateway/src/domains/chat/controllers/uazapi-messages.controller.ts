import {
  BadRequestException,
  Body,
  Controller,
  Headers,
  Post,
  UseGuards,
  UsePipes,
  ValidationPipe,
} from '@nestjs/common';
import { InternalApiKeyGuard } from '../../realtime/guards/internal-api-key.guard';
import { UazapiClient } from '../providers/uazapi/uazapi.client';
import { SendTextDto } from '../dto/send-text.dto';
import { SendFileDto } from '../dto/send-file.dto';
import { SendPresenceDto } from '../dto/send-presence.dto';
import { DownloadMediaDto } from '../dto/download-media.dto';

/**
 * UazapiMessagesController
 *
 * Gerencia envio de mensagens de texto, mídia e arquivos
 * via provider Uazapi, incluindo normalização de payload.
 */
@Controller({ path: 'send', version: '1' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class UazapiMessagesController {
  /**
   * Inicializa o controller de mensagens com o cliente Uazapi.
   *
   * @param client Cliente HTTP Uazapi para operacoes de envio de mensagens
   */
  constructor(private readonly client: UazapiClient) {}

  /**
   * Envia mensagem de texto via Uazapi.
   *
   * @param token Token da instancia
   * @param body Payload da mensagem de texto
   * @returns Resultado do envio
   */
  @Post('text')
  sendText(@Headers('token') token: string, @Body() body: SendTextDto) {
    return this.client.sendText(
      token,
      body as unknown as Record<string, unknown>,
    );
  }

  /**
   * Envia mensagem de midia via Uazapi.
   *
   * @param token Token da instancia
   * @param body Payload da mensagem de midia
   * @returns Resultado do envio
   */
  @Post('media')
  sendMedia(@Headers('token') token: string, @Body() body: SendFileDto) {
    return this.forwardSendFile(token, body);
  }

  /**
   * Envia mensagem de arquivo via Uazapi.
   *
   * @param token Token da instancia
   * @param body Payload do arquivo a enviar
   * @returns Resultado do envio
   */
  @Post('file')
  sendFile(@Headers('token') token: string, @Body() body: SendFileDto) {
    return this.forwardSendFile(token, body);
  }

  /**
   * Encaminha payload de arquivo para a Uazapi com nomes de campo normalizados.
   * Remapeia campos da API (url, caption, fileName) para o esperado pela Uazapi
   * (file, text, docName) e valida a obrigatoriedade do campo de arquivo.
   *
   * @param token Token da instancia
   * @param body Payload do arquivo
   * @returns Resultado normalizado do envio
   * @throws BadRequestException Quando url e file estao ausentes no payload
   */
  private forwardSendFile(token: string, body: SendFileDto) {
    // Uazapi espera o campo 'file' (URL ou base64)
    const file = body.file ?? body.url;

    if (!file) {
      throw new BadRequestException('url or file is required');
    }

    const payload: Record<string, unknown> = {
      ...body,
      file,
    };

    // Remove o alias 'url' — Uazapi usa 'file'
    delete payload.url;

    // Uazapi usa 'text' para legenda, nao 'caption'
    if (payload.caption && !payload.text) {
      payload.text = payload.caption;
    }
    delete payload.caption;

    // Normaliza nome do arquivo de documento: API envia 'fileName', Uazapi espera 'docName'
    if (payload.fileName && !payload.docName) {
      payload.docName = payload.fileName;
    }
    delete payload.fileName;

    this.normalizeUnsupportedImagePayload(payload, file);

    return this.client.sendFile(token, payload);
  }

  /**
   * Normaliza MIME types de imagem nao suportados para o tipo documento.
   * WebP, SVG, HEIC, HEIF e AVIF sao enviados como documento pois
   * a Uazapi nao os suporta nativamente como imagem.
   *
   * @param payload Payload normalizado a ser ajustado
   * @param file URL do arquivo ou string base64
   */
  private normalizeUnsupportedImagePayload(
    payload: Record<string, unknown>,
    file: string,
  ): void {
    const type =
      typeof payload.type === 'string' ? payload.type.toLowerCase() : null;

    if (type !== 'image') {
      return;
    }

    const mimeType = this.resolveMimeType(payload, file);
    if (!mimeType) {
      return;
    }

    const supportedImageMimeTypes = new Set([
      'image/jpeg',
      'image/jpg',
      'image/png',
      'image/gif',
    ]);

    if (supportedImageMimeTypes.has(mimeType)) {
      return;
    }

    payload.type = 'document';
  }

  /**
   * Resolve o MIME type a partir do campo mimetype do payload,
   * do prefixo de data URI ou pela extensao da URL do arquivo.
   *
   * @param payload Payload com campo mimetype opcional
   * @param file URL do arquivo ou string base64
   * @returns MIME type resolvido ou null quando nao determinavel
   */
  private resolveMimeType(
    payload: Record<string, unknown>,
    file: string,
  ): string | null {
    if (
      typeof payload.mimetype === 'string' &&
      payload.mimetype.trim() !== ''
    ) {
      return payload.mimetype.trim().toLowerCase();
    }

    if (file.startsWith('data:')) {
      const match = /^data:([^;]+);base64,/i.exec(file);
      if (match && match[1]) {
        return match[1].trim().toLowerCase();
      }
    }

    return this.inferMimeTypeFromUrl(file);
  }

  /**
   * Infere o MIME type pela extensao da URL do arquivo.
   * Opera apenas em URLs HTTP/HTTPS; retorna null para outros esquemas.
   *
   * @param file URL do arquivo
   * @returns MIME type inferido ou null quando a extensao nao e reconhecida
   */
  private inferMimeTypeFromUrl(file: string): string | null {
    if (file.startsWith('http://') || file.startsWith('https://')) {
      const normalized = file.split('?')[0].toLowerCase();

      if (normalized.endsWith('.jpg') || normalized.endsWith('.jpeg')) {
        return 'image/jpeg';
      }

      if (normalized.endsWith('.png')) {
        return 'image/png';
      }

      if (normalized.endsWith('.gif')) {
        return 'image/gif';
      }

      if (normalized.endsWith('.webp')) {
        return 'image/webp';
      }

      if (normalized.endsWith('.svg')) {
        return 'image/svg+xml';
      }

      if (normalized.endsWith('.heic')) {
        return 'image/heic';
      }

      if (normalized.endsWith('.heif')) {
        return 'image/heif';
      }

      if (normalized.endsWith('.avif')) {
        return 'image/avif';
      }
    }

    return null;
  }
}

/**
 * UazapiPresenceController
 *
 * Gerencia envio de presence e download de mídia
 * via provider Uazapi.
 */
@Controller({ path: 'message', version: '1' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class UazapiPresenceController {
  constructor(private readonly client: UazapiClient) {}

  /**
   * Envia atualizacao de presenca (digitando/gravando) via Uazapi.
   *
   * @param token Token da instancia
   * @param body Payload de atualizacao de presenca
   * @returns Resultado do envio
   */
  @Post('presence')
  sendPresence(@Headers('token') token: string, @Body() body: SendPresenceDto) {
    return this.client.sendPresence(
      token,
      body as unknown as Record<string, unknown>,
    );
  }

  /**
   * Faz download de midia recebida via Uazapi.
   *
   * @param token Token da instancia
   * @param body Payload com identificacao da midia a baixar
   * @returns Resultado do download
   */
  @Post('download')
  downloadMedia(
    @Headers('token') token: string,
    @Body() body: DownloadMediaDto,
  ) {
    return this.client.downloadMedia(
      token,
      body as unknown as Record<string, unknown>,
    );
  }
}
